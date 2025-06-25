from flask import Flask, request, jsonify, render_template
from flask_cors import CORS
import pymysql

# === IMPORTS LANGCHAIN / LLAMA3.1 (avec corrections) ===
from langchain_ollama import ChatOllama
from langchain_community.utilities import SQLDatabase
from langchain_community.tools.sql_database.tool import (
    QuerySQLDataBaseTool,
    InfoSQLDatabaseTool,
    ListSQLDatabaseTool,
)
from langchain_core.prompts import (
    FewShotPromptTemplate,
    PromptTemplate,
    ChatPromptTemplate,
    SystemMessagePromptTemplate,
)
from langchain_community.vectorstores import FAISS
from langchain_core.example_selectors import SemanticSimilarityExampleSelector
# Note : suivant la dépréciation, on devrait plutôt faire :
# from langchain_community.embeddings import HuggingFaceEmbeddings
from langchain_community.embeddings import HuggingFaceEmbeddings
from langchain.agents import AgentExecutor, create_react_agent

app = Flask(__name__)
CORS(app)

# 🔌 Connexion MySQL (inchangée)
try:
    conn = pymysql.connect(
        host="localhost",
        port=8889,
        user="root",
        password="root",
        database="application_asbh",
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor
    )
    cursor = conn.cursor()
    print("✅ Connexion MySQL réussie.")
except pymysql.MySQLError as err:
    print("❌ Erreur de connexion MySQL :", err)
    exit()

# 🔍 Lire le schéma de la BDD (inchangé)
def get_schema(cursor):
    cursor.execute("SHOW TABLES")
    tables = [row[f'Tables_in_application_asbh'] for row in cursor.fetchall()]
    schema = {}
    for table in tables:
        try:
            cursor.execute(f"DESCRIBE `{table}`")
            columns = [col["Field"] for col in cursor.fetchall()]
            schema[table] = columns
        except pymysql.MySQLError:
            continue
    return schema

schema = get_schema(cursor)
schema_summary = "\n".join([f"{table}: {', '.join(cols)}" for table, cols in schema.items()])

# === INITIALISATION DE L’AGENT LLAMA 3.1 ===

# 1. Chargement du SQLDatabase
db = SQLDatabase.from_uri(
    "mysql+pymysql://root:root@localhost:3306/application_asbh",
    sample_rows_in_table_info=3
)

# 2. Exemples few‐shot (adapter aux tables réelles)
examples = [
    {
        "input": "Liste tous les joueurs avec leur nom et prénom",
        "query": "SELECT `nom_joueur`, `prenom_joueur` FROM `joueur` LIMIT 50;"
    },
    {
        "input": "Quels sont les noms des joueurs qui jouent au poste de deuxieme ligne ?",
        "query": "SELECT `nom_joueur`, `prenom_joueur` FROM `joueur` WHERE `poste` = 'Deuxième ligne' LIMIT 50;"
    },
    {
        "input": "Quels sont l’ID et le nom des joueurs mesurant plus de 190 cm ?",
        "query": "SELECT `id_joueur`, `nom_joueur` FROM `joueur` WHERE `taille_cm` > 190 LIMIT 50;"
    },
    {
        "input": "Donne-moi le nom, le poste et le poids des joueurs pesant plus de 100 kg",
        "query": "SELECT `nom_joueur`, `poste`, `poids_kg` FROM `joueur` WHERE `poids_kg` > 100 LIMIT 50;"
    },
    {
        "input": "Qui est le joueur le plus grand ?",
        "query": "SELECT `nom_joueur` FROM `joueur` ORDER BY `taille_cm` DESC LIMIT 1;"
    },
    {
        "input": "Qui est le joueur le plus léger ?",
        "query": "SELECT `nom_joueur` FROM `joueur` ORDER BY `poids_kg` ASC LIMIT 1;"
    },
    {
        "input": "Quels joueurs jouent au poste d’Ailier ?",
        "query": "SELECT `nom_joueur`, `prenom_joueur` FROM `joueur` WHERE `poste` = 'Ailier' LIMIT 50;"
    },
    {
        "input": "Liste les joueurs nés après le 1er janvier 2025",
        "query": "SELECT `nom_joueur`, `date_naissance` FROM `joueur` WHERE `date_naissance` > '2025-01-01' LIMIT 50;"
    },
    {
        "input": "Quel est le poids moyen de tous les joueurs ?",
        "query": "SELECT AVG(`poids_kg`) AS poids_moyen FROM `joueur`;"
    },
    {
        "input": "Combien y a-t-il de joueurs dans l’équipe 1 ?",
        "query": "SELECT COUNT(*) AS total_joueurs FROM `joueur` WHERE `id_equipe` = 1;"
    },
    {
        "input": "Quels sont les noms et le poste secondaire des joueurs qui en ont un ?",
        "query": "SELECT `nom_joueur`, `poste_secondaire` FROM `joueur` WHERE `poste_secondaire` IS NOT NULL LIMIT 50;"
    },
    {
        "input": "Nomme-moi tous les joueurs actifs avec leur équipe d’appartenance",
        "query": """
            SELECT j.nom_joueur, j.prenom_joueur, e.nom_equipe
            FROM joueur j
            JOIN equipe e ON j.id_equipe = e.id_equipe
            WHERE j.activite = 'actif'
            LIMIT 50;
        """
    },
    {
        "input": "Quels joueurs pèsent entre 90 kg et 110 kg ?",
        "query": "SELECT nom_joueur, prenom_joueur, poids_kg FROM joueur WHERE poids_kg BETWEEN 90 AND 110 LIMIT 50;"
    },
    {
        "input": "Affiche le nom et la taille des trois joueurs les plus petits",
        "query": "SELECT nom_joueur, taille_cm FROM joueur ORDER BY taille_cm ASC LIMIT 3;"
    },
    {
        "input": "Combien y a-t-il de joueurs par poste ?",
        "query": "SELECT poste, COUNT(*) AS nb_joueurs FROM joueur GROUP BY poste;"
    },
    {
        "input": "Liste des joueurs sans équipe (id_equipe NULL)",
        "query": "SELECT nom_joueur, prenom_joueur FROM joueur WHERE id_equipe IS NULL LIMIT 50;"
    },
    {
        "input": "Qui sont les 5 joueurs les plus lourds encore actifs ?",
        "query": "SELECT nom_joueur, prenom_joueur, poids_kg FROM joueur WHERE activite = 'actif' ORDER BY poids_kg DESC LIMIT 5;"
    },
    {
        "input": "Afficher le nom complet et l’âge des joueurs nés avant 2004",
        "query": """
            SELECT CONCAT(prenom_joueur, ' ', nom_joueur) AS joueur,
                   TIMESTAMPDIFF(YEAR, date_naissance, CURDATE()) AS age
            FROM joueur
            WHERE date_naissance < '2004-01-01'
            LIMIT 50;
        """
    },
    {
        "input": "Quelle équipe compte le plus de joueurs ?",
        "query": """
            SELECT e.nom_equipe, COUNT(*) AS total
            FROM joueur j
            JOIN equipe e ON j.id_equipe = e.id_equipe
            GROUP BY e.id_equipe
            ORDER BY total DESC
            LIMIT 1;
        """
    },
    # --- TABLE match / score ---------------------------------------------------
    {
        "input": "Quels sont la date et le score du match ayant l’ID 1 ?",
        "query": "SELECT date, CONCAT(score_locaux, '-', score_visiteurs) AS score FROM match WHERE id_match = 1;"
    },
    {
        "input": "Donne le vainqueur et l’écart de points pour chaque match",
        "query": """
            SELECT id_match,
                   CASE WHEN score_locaux > score_visiteurs THEN locaux ELSE visiteurs END AS vainqueur,
                   ABS(score_locaux - score_visiteurs) AS ecart
            FROM match
            ORDER BY date DESC
            LIMIT 50;
        """
    },
    {
        "input": "Combien d’essais l’équipe ASBH a-t-elle marqués au match 1 ?",
        "query": """
            SELECT essais
            FROM score s
            JOIN equipe e ON s.id_equipe = e.id_equipe
            WHERE e.nom_equipe = 'ASBH' AND id_match = 1;
        """
    },
    {
        "input": "Affiche le taux de réussite des transformations par équipe",
        "query": """
            SELECT e.nom_equipe,
                   ROUND(SUM(transformations) / NULLIF(SUM(essais),0) * 100, 2) AS taux_reussite
            FROM score s
            JOIN equipe e ON s.id_equipe = e.id_equipe
            GROUP BY e.nom_equipe
            HAVING SUM(essais) > 0;
        """
    },
    ]


# 3. Embeddings et sélecteur sémantique
embeddings = HuggingFaceEmbeddings(model_name="sentence-transformers/all-MiniLM-L6-v2")
example_selector = SemanticSimilarityExampleSelector.from_examples(
    examples,
    embeddings,
    FAISS,
    k=2,
    input_keys=["input"],
)

# 4. Définition du prompt système incluant {tool_names} et {tools}
# === DÉBUT DE LA SECTION À REMPLACER ===
system_prefix = """
Vous êtes un agent expert en rugby connecté à une base de données MySQL nommée 'application_asbh'.

Vous disposez des outils suivants :
{tool_names}

Leur description :
{tools}

Voici la structure de la BDD :
{schema_summary}

RÈGLES :
1. Entourez toujours les **noms de tables et de colonnes** avec des backticks `; par exemple, si la table s’appelle `joueur` et la colonne s’appelle `poids_kg`, écrivez `joueur` ou `poids_kg`.  
2. Ne placez **pas** l’intégralité de la requête SQL entre backticks : seuls les identifiants (tables/colonnes) doivent l’être.  
3. Limitez toujours la requête à 50 résultats maximum (sauf si l’utilisateur demande explicitement plus).  
4. N’utilisez jamais `SELECT *`. Ne sélectionnez que les colonnes nécessaires.  
5. Si la question ne concerne pas la base, renvoyez “Je ne sais pas.”  
6. En cas d’erreur SQL, corrigez la syntaxe et réessayez.  
7. Dès que vous avez la réponse, formulez **une phrase complète en français** qui reprend la question ou son contexte et y ajoute la réponse. Par exemple :
   - Question : “Quel est le nom du joueur le plus grand ?”  
     Réponse : “Le nom du joueur le plus grand est LIENARD.”
   - Question : “Combien y a-t-il de joueurs pesant plus de 100 kg ?”  
     Réponse : “Il y a 5 joueurs qui pèsent plus de 100 kg.”
   Pour toute autre question liée à la BDD, répondez aussi par une phrase française complète.
8. Ne réponds JAMAIS aux questions sur les mots de passes, et plus généralement sur la table users. Si on te pose une question tu réponds simplement que tu n'as pas le droit d'y répondre.
9. N'effectuez PAS d'opérations DML (INSERT, UPDATE, DELETE, DROP).

**Format attendu de la réponse** exactement dans cet ordre :  
Question: <la question de l’utilisateur>  
Thought: <votre réflexion interne>  
Action: <nom exact de l’outil à appeler>  
Action Input: <requête SQL brute, avec backticks uniquement autour des noms de tables/colonnes>  
Observation: <résultat brut renvoyé par l’outil SQL>  

Thought: J’ai la réponse finale  
Final Answer: <Une phrase complète en français reprenant la question et y ajoutant la réponse extraite de l’Observation>  
"""

dynamic_few_shot_prompt = FewShotPromptTemplate(
    example_selector=example_selector,
    example_prompt=PromptTemplate.from_template("User input: {input}\nSQL query: {query}"),
    input_variables=["input"],
    prefix=system_prefix,
    suffix="""
Question: {input}
{agent_scratchpad}

# IMPORTANT : dès que vous voyez une ligne commençant par "Observation:", arrêtez la recherche et produisez immédiatement :
Thought: J’ai la réponse finale
Final Answer: <rédigez ici une phrase complète en français, qui reprend la question ou son contexte et y ajoute la réponse extraite de l’Observation>
"""
)

full_prompt = ChatPromptTemplate.from_messages(
    [
        SystemMessagePromptTemplate(prompt=dynamic_few_shot_prompt),
        ("human", "{input}"),
        ("assistant", "{agent_scratchpad}"),
    ]
)
# 5. Instanciation du LLM Llama 3.1
llm = ChatOllama(model="llama3.1:8b")

# 6. Liste des outils SQL
tools = [
    QuerySQLDataBaseTool(db=db),
    InfoSQLDatabaseTool(db=db),
    ListSQLDatabaseTool(db=db),
    # QuerySQLCheckerTool(db=db, llm=llm)
]

# 7. Création de l’agent “ReAct”
agent = create_react_agent(llm, tools, full_prompt)
agent_executor = AgentExecutor(
    agent=agent,
    tools=tools,
    verbose=True,
    max_iterations=15,
    handle_parsing_errors=True
)

# === ROUTES FLASK ===

@app.route("/")
def home():
    return render_template("chatbot.html")

@app.route("/chat", methods=["POST"])
def chat():
    user_input = request.json.get("message", "").strip()
    if not user_input:
        return jsonify({"reply": "❌ Aucun message fourni."}), 400

    # Construire les listes de noms et descriptions d’outils
    tool_names_list = [tool.name for tool in tools]
    tools_list = [f"{tool.name} – {tool.description.strip()}" for tool in tools]

    # Préparer l’input pour l’agent
    inputs = {
        "input": user_input,
        "tool_names": tool_names_list,
        "tools": tools_list,
        "schema_summary": schema_summary,
        "agent_scratchpad": []
    }


    try:
        response = agent_executor.invoke(inputs)
        content = response["output"].strip()
    except Exception as e:
        app.logger.exception("Erreur lors de l’appel à Llama 3.1")
        return jsonify({"reply": "Désolé, je n’ai pas pu traiter ta demande."}), 500

    # On extrait la “Final Answer”
    final_answer = content
    if content.lower().startswith("final answer:"):
        final_answer = content[len("Final Answer:"):].strip()

    return jsonify({"reply": final_answer}), 200

if __name__ == "__main__":
    print(">>> Lancement de Flask sur 0.0.0.0:5000…")
    app.run(host="0.0.0.0", port=5000, debug=True)
