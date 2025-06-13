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
        port=3306,
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
    "mysql+pymysql://root:root@localhost:3306/aya_application_asbh",
    sample_rows_in_table_info=3
)

# 2. Exemples few‐shot (adapter aux tables réelles)
examples = [
    {
        "input": "Liste tous les joueurs avec leur nom et prénom",
        "query": "SELECT `nom_joueur`, `prenom_joueur` FROM `joueur` LIMIT 50;"
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
        "input": "Quel est le poids moyen par poste secondaire ?",
        "query": "SELECT poste_secondaire, AVG(poids_kg) AS poids_moyen FROM joueur WHERE poste_secondaire <> '' GROUP BY poste_secondaire;"
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
    # --- TABLE courir ----------------------------------------------------------
    {
        "input": "Quelle distance totale a parcourue le joueur 1 dans le match 1 ?",
        "query": """
            SELECT SUM(distance_totale) AS distance_m
            FROM courir
            WHERE id_joueur = 1 AND id_match = 1;
        """
    },
    {
        "input": "Top 5 des joueurs ayant atteint la plus grande Vmax",
        "query": """
            SELECT id_joueur, MAX(vmax) AS vmax
            FROM courir
            GROUP BY id_joueur
            ORDER BY vmax DESC
            LIMIT 5;
        """
    },
    {
        "input": "Donne la distance moyenne par minute pour chaque joueur (match 1)",
        "query": """
            SELECT id_joueur,
                   ROUND(SUM(distance_totale) / SUM(temps_de_jeu), 2) AS m_par_min
            FROM courir
            WHERE id_match = 1
            GROUP BY id_joueur
            LIMIT 50;
        """
    },
    # --- TABLE idp -------------------------------------------------------------
    {
        "input": "Quel joueur a obtenu le meilleur IDP au match 1 ?",
        "query": """
            SELECT j.nom_joueur, j.prenom_joueur, i.idp
            FROM idp i
            JOIN joueur j ON j.id_joueur = i.id_joueur
            WHERE i.id_match = 1
            ORDER BY i.idp DESC
            LIMIT 1;
        """
    },
    {
        "input": "Lister les IDP par poste pour le match 1",
        "query": "SELECT poste, AVG(idp) AS idp_moyen FROM idp WHERE id_match = 1 GROUP BY poste;"
    },
    {
        "input": "Quels joueurs ont joué plus de 60 minutes et un IDP > 50 ?",
        "query": """
            SELECT j.nom_joueur, j.prenom_joueur, i.idp, i.minutes_jouees
            FROM idp i
            JOIN joueur j ON j.id_joueur = i.id_joueur
            WHERE i.minutes_jouees > 60 AND i.idp > 50
            LIMIT 50;
        """
    },
    # --- TABLE export_stat_match ----------------------------------------------
    {
        "input": "Nombre total de plaquages par joueur au match 1",
        "query": """
            SELECT id_joueur, valeur AS total_plaquages
            FROM export_stat_match
            WHERE action = 'Total Plaquage' AND id_match = 1
            ORDER BY total_plaquages DESC
            LIMIT 50;
        """
    },
    {
        "input": "Quels joueurs ont raté plus de 2 plaquages ?",
        "query": """
            SELECT id_joueur, valeur AS plaquages_manques
            FROM export_stat_match
            WHERE action = 'Total Plaquage manqué' AND valeur > 2
            LIMIT 50;
        """
    },
    # --- TABLE fin_actions_collectives ----------------------------------------
    {
        "input": "Total de rucks pour chaque équipe",
        "query": """
            SELECT id_equipe, SUM(total) AS total_rucks
            FROM fin_actions_collectives
            WHERE action = 'Ruck'
            GROUP BY id_equipe;
        """
    },
    {
        "input": "Quelle équipe a concédé le plus de CPP en mêlée ?",
        "query": """
            SELECT id_equipe, SUM(total) AS cpp_concedes
            FROM fin_actions_collectives
            WHERE action = 'Mêlée CPP Concédé'
            GROUP BY id_equipe
            ORDER BY cpp_concedes DESC
            LIMIT 1;
        """
    },
    # --- TABLE localisation ----------------------------------------------------
    {
        "input": "Répartition des mêlées de l’équipe 1 par portion de terrain",
        "query": """
            SELECT portion_terrain, SUM(valeur) AS nb_mêlées
            FROM localisation
            WHERE id_equipe = 1 AND action = 'Mêlée'
            GROUP BY portion_terrain;
        """
    },
    {
        "input": "Actions jouées dans la zone \"22-40\" durant les 15-55 min",
        "query": """
            SELECT DISTINCT action
            FROM localisation
            WHERE portion_terrain = '22-40' AND temps = '15-55';
        """
    },
    # --- TABLE points ----------------------------------------------------------
    {
        "input": "Top 3 des secteurs rapportant le plus de points positifs (toutes équipes)",
        "query": """
            SELECT actions, SUM(points_positifs) AS pts_pos
            FROM points
            GROUP BY actions
            ORDER BY pts_pos DESC
            LIMIT 3;
        """
    },
    {
        "input": "Points totaux, positifs et négatifs de l’équipe CAR",
        "query": """
            SELECT points_total, points_positifs, points_negatifs
            FROM points p
            JOIN equipe e ON p.id_equipe = e.id_equipe
            WHERE e.nom_equipe = 'CAR';
        """
    },
    # --- TABLE possession & temps_effectif ------------------------------------
    {
        "input": "Quelle équipe a eu la plus longue possession en 1re mi-temps (match 1) ?",
        "query": """
            SELECT e.nom_equipe, p.possession_mt_1_e
            FROM possession_mt_1 p
            JOIN equipe e ON p.id_equipe = e.id_equipe
            WHERE p.id_match = 1
            ORDER BY p.possession_mt_1_e DESC
            LIMIT 1;
        """
    },
    {
        "input": "Affiche le temps effectif de jeu par équipe (match 1)",
        "query": """
            SELECT e.nom_equipe, t.temps_effectif_e
            FROM temps_effectif t
            JOIN equipe e ON t.id_equipe = e.id_equipe
            WHERE t.id_match = 1;
        """
    },
    # --- TABLE users -----------------------------------------------------------
    {
        "input": "Lister tous les comptes utilisateurs et leur rôle",
        "query": "SELECT username, role FROM users LIMIT 50;"
    },
    # --- REQUÊTES MULTI-TABLES AVANCÉES ---------------------------------------
    {
        "input": "Nom, poste et IDP des joueurs d’ASBH ayant couru plus de 5 000 m",
        "query": """
            SELECT j.nom_joueur, j.poste, i.idp
            FROM joueur j
            JOIN idp i  ON i.id_joueur = j.id_joueur
            JOIN courir c ON c.id_joueur = j.id_joueur AND c.periode = 'Match entier'
            WHERE j.id_equipe = 1     -- ASBH
              AND c.distance_totale > 5000;
        """
    },
    {
        "input": "Pour chaque match, calcule le total d’essais marqués (toutes équipes)",
        "query": """
            SELECT id_match, SUM(essais) AS total_essais
            FROM score
            GROUP BY id_match
            ORDER BY id_match;
        """
    },
    {
        "input": "Trouve la moyenne de nb_accel par poste (match 1)",
        "query": """
            SELECT j.poste, ROUND(AVG(c.nb_accel),2) AS accel_moy
            FROM courir c
            JOIN joueur j ON j.id_joueur = c.id_joueur
            WHERE c.id_match = 1 AND c.periode = 'Match entier'
            GROUP BY j.poste;
        """
    },
    {
        "input": "Quels joueurs ont à la fois un IDP > 60 et plus de 10 plaquages ?",
        "query": """
            SELECT j.nom_joueur, i.idp, ps.valeur AS plaquages
            FROM idp i
            JOIN joueur j          ON j.id_joueur = i.id_joueur
            JOIN export_stat_match ps ON ps.id_joueur = j.id_joueur
            WHERE i.idp > 60
              AND ps.action = 'Total Plaquage'
              AND ps.valeur > 10
            LIMIT 50;
        """
    },
    {
        "input": "Classe les équipes par total de points négatifs (descendant)",
        "query": """
            SELECT e.nom_equipe, SUM(points_negatifs) AS pts_neg
            FROM points p
            JOIN equipe e ON e.id_equipe = p.id_equipe
            GROUP BY e.id_equipe
            ORDER BY pts_neg DESC;
        """
    },
    {
        "input": "Durée totale de possession (1re + 2e mi-temps) pour l’équipe 2 (match 1)",
        "query": """
            SELECT SEC_TO_TIME(
                     TIME_TO_SEC(p1.possession_mt_1_e) + TIME_TO_SEC(p2.possession_mt_2_e)
                   ) AS possession_totale
            FROM possession_mt_1 p1
            JOIN possession_mt_2 p2 ON p1.id_equipe = p2.id_equipe AND p1.id_match = p2.id_match
            WHERE p1.id_equipe = 2 AND p1.id_match = 1;
        """
    },
    {
        "input": "Nombre de rucks gagnés par ASBH (équipe 1)",
        "query": """
            SELECT SUM(total) AS rucks_gagnes
            FROM fin_actions_collectives
            WHERE id_equipe = 1 AND action = 'Ruck Conservation';
        """
    },
    {
        "input": "Liste des matches où l’écart de score ≥ 20 points",
        "query": """
            SELECT id_match, locaux, visiteurs, score_locaux, score_visiteurs
            FROM match
            WHERE ABS(score_locaux - score_visiteurs) >= 20;
        """
    },
    {
        "input": "Joueurs ayant effectué plus de 40 accélérations (toutes mi-temps confondues)",
        "query": """
            SELECT id_joueur, SUM(nb_accel) AS total_accel
            FROM courir
            GROUP BY id_joueur
            HAVING total_accel > 40
            ORDER BY total_accel DESC
            LIMIT 50;
        """
    },
    {
        "input": "Affiche, pour chaque équipe, le ratio essais/transformations réussies",
        "query": """
            SELECT e.nom_equipe,
                   SUM(transformations) / NULLIF(SUM(essais),0) AS ratio
            FROM score s
            JOIN equipe e ON e.id_equipe = s.id_equipe
            GROUP BY e.nom_equipe;
        """
    },
    {
        "input": "Nombre total de fautes règlementaires commises par équipe",
        "query": """
            SELECT id_equipe, SUM(total) AS fautes
            FROM fin_actions_collectives
            WHERE action = 'Faute règlement CPP Concédé'
            GROUP BY id_equipe
            ORDER BY fautes DESC;
        """
    },
    {
        "input": "Quels joueurs ont commis une faute de brutalité ?",
        "query": """
            SELECT DISTINCT id_joueur
            FROM export_stat_match
            WHERE action LIKE 'Faute règlement - Brutalité';
        """
    },
    {
        "input": "Top 10 des joueurs ayant la plus grande distance/minute (match entier)",
        "query": """
            SELECT id_joueur,
                   ROUND(SUM(distance_totale) / SUM(temps_de_jeu),2) AS dist_par_min
            FROM courir
            WHERE periode = 'Match entier'
            GROUP BY id_joueur
            ORDER BY dist_par_min DESC
            LIMIT 10;
        """
    }
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
