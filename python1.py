import pandas as pd 
import unicodedata
import os 
import sys
import io
import re
input_path = sys.argv[1]  # récupère le chemin transmis depuis PHP
output_path = sys.argv[2]  # où enregistrer le fichier transformé

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

def transpose_and_flatten_partially(file_path, output_path=None):
    xls = pd.ExcelFile(file_path)
    sheets = xls.sheet_names
    cleaned_sheets = {}

    for i, sheet in enumerate(sheets):
        if i == 0:
        
            df = xls.parse(sheet, header=None)

            # Nettoyage : supprimer lignes/colonnes vides
            df.dropna(how='all', axis=0, inplace=True)
            df.dropna(how='all', axis=1, inplace=True)

            # Transposer
            df = df.T

            # Utiliser la première ligne comme en-têtes
            df.columns = df.iloc[0]
            df = df.drop(df.index[0]).reset_index(drop=True)

            # Nettoyage des noms de colonnes
            df.columns = [str(col).strip().replace("\n", "_").replace(" ", "_") for col in df.columns]

            # Séparer colonnes fixes et dynamiques
            fixed_columns = df.columns[:13]  # colonnes A à M
            dynamic_columns = df.columns[13:]  # colonnes N à T (ou plus)

            df_fixed = df[fixed_columns].copy()

            # Gestion des colonnes dynamiques
            if not df[dynamic_columns].empty and df.shape[0] >= 2:
                team_names = df.loc[0:1, dynamic_columns[0]].index.tolist()
                team_labels = df.loc[team_names, dynamic_columns[0]].tolist()

                flat_data = {}
                for col in dynamic_columns:
                    for idx, team in zip(team_names, team_labels):
                        flat_data[f"{col}_{team}"] = [df.loc[idx, col]]

                df_dynamic = pd.DataFrame(flat_data)

                # Supprimer les anciennes colonnes d'équipe (ex: noms d'équipe en double)
                df_dynamic.drop(columns=[f"{dynamic_columns[0]}_{team}" for team in team_labels],
                                inplace=True, errors='ignore')

                # Reconstituer le DataFrame avec colonnes fixes + dynamiques
                df = pd.concat([df_fixed.reset_index(drop=True), df_dynamic], axis=1)

            # === AJOUTER LES COLONNES APLATIES (1 ligne) À DROITE ===

            # Colonnes à aplatir (contenant 3 lignes)
            cols_to_flatten = ['Temps_effectif', 'Possession_MT_1', 'Possession_MT_2']

            # Extraire les 3 lignes pour ces colonnes (en haut du fichier)
            flatten_values = df.loc[:2, cols_to_flatten]

            # Créer un dictionnaire aplati (1 seule ligne, 9 colonnes)
            labels = ['Beziers', 'Equipe_adverse', 'Total']
            flattened_data = {
                f"{col}_{label}": flatten_values.iloc[i][col]
                for col in cols_to_flatten
                for i, label in enumerate(labels)
            }

            df_flattened = pd.DataFrame([flattened_data])

            # Supprimer les colonnes d'origine
            df = df.drop(columns=cols_to_flatten, errors='ignore')

            # Ajouter les colonnes aplaties à droite — uniquement sur la première ligne
            for col in df_flattened.columns:
                df[col] = None  # Initialiser colonne vide
            df.loc[0, df_flattened.columns] = df_flattened.iloc[0]  # Insérer les valeurs seulement ligne 0

            # Normalisation finale des noms de colonnes
            df.columns = [
                unicodedata.normalize('NFKD', str(col))  # Convertir d'abord en chaîne de caractères
                .encode('ASCII', 'ignore')  # Enlever les accents
                .decode('utf-8')  # Convertir en UTF-8
                .lower()  # Mettre en minuscule
                .replace(" ", "_")  # Remplacer les espaces par des underscores
                for col in df.columns
            ]

            # Enregistrer le résultat dans le dictionnaire
            cleaned_sheets[sheet] = df

            

        elif i == 1:
            df = xls.parse(sheet, header=None)

            
            # Supprimer les lignes 2 et 3 (index 1 et 2)
            df = df.drop(index=[0, 1]).reset_index(drop=True)

            # Garder uniquement les 4 premières colonnes
            df = df.iloc[:, :4]

            # Attribuer manuellement les noms des colonnes
            df.columns = ["poste", "nom", "prenom", "temps_de_jeu"]

            cleaned_sheets[sheet] = df

        elif i == 2:
            df = xls.parse(sheet, header=None)

            # Nettoyage : supprimer lignes/colonnes vides
            df.dropna(how='all', axis=0, inplace=True)
            df.dropna(how='all', axis=1, inplace=True)

            # Récupérer dynamiquement les noms des équipes
            equipe_asbh = df.iloc[0, 1]  # Exemple pour ASBH
            equipe_autre = df.iloc[0, 5]  # Exemple pour l'autre équipe

            # Supprimer la ligne 0 qui contient les noms des équipes
            df = df.drop(index=[0]).reset_index(drop=True)

            # Garder les 4 premières colonnes pour ASBH et les 4 suivantes pour l'autre équipe
            asbh_data = df.iloc[:, 1:5]  # Colonnes 1 à 4 pour ASBH (V1 à V4)
            autre_data = df.iloc[:, 5:9]  # Colonnes 5 à 8 pour l'autre équipe (V1 à V4)

            # Récupérer les noms des actions dans la colonne 0 après suppression de la ligne 0
            actions = df.iloc[:, 0]  # Toutes les actions dans la première colonne après la suppression

            # Renommer les colonnes pour chaque équipe
            asbh_data.columns = ["total", "positif", "neutre", "negatif"]
            autre_data.columns = ["total", "positif", "neutre", "negatif"]

            # Ajouter les informations d'équipe et d'action
            asbh_data["équipe"] = equipe_asbh
            autre_data["équipe"] = equipe_autre
            asbh_data["action"] = actions
            autre_data["action"] = actions

            # Fusionner les données d'ASBH et de l'autre équipe
            df_long = pd.concat([asbh_data, autre_data], ignore_index=True)

            # Réorganiser les colonnes pour avoir "action", "équipe", "V1", "V2", "V3", "V4"
            df_long = df_long[["action", "équipe", "total", "positif", "neutre", "negatif"]]

            # Mettez à jour cleaned_sheets avec la feuille modifiée
            cleaned_sheets[sheet] = df_long

        elif i == 3:
            df = xls.parse(sheet, header=None)

            # Nettoyage : supprimer lignes/colonnes vides
            df.dropna(how='all', axis=0, inplace=True)
            df.dropna(how='all', axis=1, inplace=True)

            # Récupérer dynamiquement les noms des équipes
            equipe_asbh = df.iloc[0, 1]  # Exemple pour ASBH
            equipe_autre = df.iloc[0, 4]  # Exemple pour l'autre équipe

            # Supprimer la ligne inutiles 
            df = df.drop(index=[0, 12, 24]).reset_index(drop=True)
            
            # Récupérer les colonnes d'actions
            colonnes_actions = [0, 7, 14, 21]
            actions = pd.concat([df.iloc[:, col] for col in colonnes_actions], ignore_index=True)

            # Supprimer les valeurs NaN
            actions = actions.dropna().reset_index(drop=True)

            # récupérer les données asbh
            asbh_data = df.iloc[:, [1, 2, 3, 8, 9, 10, 15, 16, 17, 22, 23, 24]]
            
            # Définir les blocs de colonnes par groupe de 3
            blocs = [
                [1, 2, 3],    # bloc 1
                [8, 9, 10],   # bloc 2
                [15, 16, 17], # bloc 3
                [22, 23, 24]  # bloc 4
            ]

            # Fusionner tous les blocs verticalement avec noms standardisés
            asbh_data = pd.concat([
                df.iloc[:, cols].set_axis(["Total", "MT1", "MT2"], axis=1)
                for cols in blocs
            ], ignore_index=True)

            # Supprimer les lignes entièrement vides
            asbh_data.dropna(how='all', inplace=True)

            # récupérer les données autre
            autre_data = df.iloc[:, [4, 5, 6, 11, 12, 13, 18, 19, 20, 25, 26, 27]]
            
            # Définir les blocs de colonnes par groupe de 3
            blocs = [
                [4, 5, 6],    # bloc 1
                [11, 12, 13],   # bloc 2
                [18, 19, 20], # bloc 3
                [25, 26, 27]  # bloc 4
            ]

            # Fusionner tous les blocs verticalement avec noms standardisés
            autre_data = pd.concat([
                df.iloc[:, cols].set_axis(["Total", "MT1", "MT2"], axis=1)
                for cols in blocs
            ], ignore_index=True)

            # Supprimer les lignes entièrement vides
            autre_data.dropna(how='all', inplace=True)
            asbh_data = asbh_data.reset_index(drop=True)
            autre_data = autre_data.reset_index(drop=True)
            actions = actions.reset_index(drop=True)

            # Ajouter les informations d'équipe et d'action
            asbh_data["équipe"] = equipe_asbh
            autre_data["équipe"] = equipe_autre
            asbh_data["action"] = actions
            autre_data["action"] = actions

            # Fusionner les données d'ASBH et de l'autre équipe
            df_long = pd.concat([asbh_data, autre_data], ignore_index=True)
            
            cleaned_sheets[sheet] = df_long 

        elif i == 4:
            df = xls.parse(sheet, header=None)

            # Nettoyage : supprimer lignes/colonnes vides
            df.dropna(how='all', axis=0, inplace=True)
            df.dropna(how='all', axis=1, inplace=True)

            df.drop(df.columns[[1, 8, 10, 17]], axis=1, inplace=True)

            df = df.reset_index(drop=True)

            # Coordonnées des cellules à extraire (ligne, colonne)
            coords = [(0, 0), (4, 0), (8, 0), (12, 0), (16, 0),
                    (0, 7), (4, 7), (8, 7), (12, 7), (16, 7)]

            # Récupérer les valeurs dans une liste
            actions = [df.iat[row, col] for row, col in coords if pd.notna(df.iat[row, col])]

            # Coordonnées des portions de terrain dans la ligne 0, colonnes 1 à 6
            terrain_coords = [(0, col) for col in range(1, 7)]  # Colonnes 1 à 6, ligne 0

            # Récupérer les valeurs dans une liste
            prop_terrain = [df.iat[row, col] for row, col in terrain_coords if pd.notna(df.iat[row, col])]

            # Coordonnées des cellules dans la colonne 1, lignes 2 à 4
            temps_coords = [(row, 0) for row in range(1, 4)]  # Lignes 2 à 4, colonne 1

            # Récupérer les valeurs dans une liste
            temps_values = [df.iat[row, col] for row, col in temps_coords if pd.notna(df.iat[row, col])]

            # Initialisation de la liste finale
            data_finale = []

            def extraire_action_et_equipe(cellule):
                actions_possibles = ["Mêlée", "Touche", "Faute technique", "Faute règlement", "Récupération"]
                if not isinstance(cellule, str):
                    return None, None

                for action in actions_possibles:
                    if cellule.startswith(action):
                        equipe = cellule.replace(action, "").strip(" -:")
                        return action, equipe
                return None, cellule.strip()



            # Actions : lignes 0, 4, 8, 12, 16
            action_rows = [0, 4, 8, 12, 16]
            terrain_cols = [1, 2, 3, 4, 5, 6]  # Ces colonnes contiennent les portions de terrain

            for action_row in action_rows:
                cellule_action = df.iat[action_row, 0]
                action, equipe = extraire_action_et_equipe(cellule_action)
                            
                for i in range(1, 4):  # Lignes de temps : 1 à 3 après chaque action
                    temps = df.iat[action_row + i, 0]
                                
                    for col in terrain_cols:
                        portion_terrain = df.iat[action_row, col]
                        valeur = df.iat[action_row + i, col]
                                    
                        if pd.notna(valeur):
                            data_finale.append({
                                'action': action,
                                'equipe': equipe,
                                'portion_terrain': portion_terrain,
                                'temps': temps,
                                'valeur': valeur
                            })



            # Traitement des actions CAR (à droite)
            terrain_cols_car = [8, 9, 10, 11, 12, 13]  # colonnes à droite

            for action_row in action_rows:
                cellule_action = df.iat[action_row, 7]
                action, equipe = extraire_action_et_equipe(cellule_action)

                for i in range(1, 4):
                    temps = df.iat[action_row + i, 0]

                    for col in terrain_cols_car:
                        portion_terrain = df.iat[action_row, col]
                        valeur = df.iat[action_row + i, col]

                        if pd.notna(valeur):
                            data_finale.append({
                                'action': action,
                                'equipe': equipe,
                                'portion_terrain': portion_terrain,
                                'temps': temps,
                                'valeur': valeur
                            })


            # Création du DataFrame final
            df_final = pd.DataFrame(data_finale)

            cleaned_sheets[sheet] = df_final

        else:
            print(f"🔸 Feuille ignorée : {sheet} (pas modifiée)")
            df = pd.read_excel(file_path, sheet_name=sheet, header=None, engine='openpyxl')

            cleaned_sheets[sheet] = df

    if output_path is None:
        output_path = file_path.replace('.xlsx', '_modifie.xlsx')
        

    with pd.ExcelWriter(output_path, engine='openpyxl') as writer:
        for i, (sheet_name, df_clean) in enumerate(cleaned_sheets.items()):
            if i in [0, 1, 2, 3, 4]:  # écrire les en-têtes pour les deux premières feuilles
                df_clean.to_excel(writer, sheet_name=sheet_name, index=False, header=True)
            else:
                df_clean.to_excel(writer, sheet_name=sheet_name, index=False, header=False)

    print(f"\n✅ Fichier bien exporté et transformé")
    #return cleaned_sheets

# === Exemple d'utilisation ===
import glob
import os

# Trouver tous les fichiers "Data Match*.xlsx" sauf ceux déjà modifiés
input_files = [f for f in glob.glob("uploads/Data Match*.xlsx") if "_modifie" not in f]

if not input_files:
    print("❌ Aucun nouveau fichier 'Data Match*.xlsx' trouvé dans le dossier uploads/")
else:
    # Prendre uniquement le fichier le plus récemment modifié
    latest_input = max(input_files, key=os.path.getmtime)
    output_file = latest_input.replace('.xlsx', '_modifie.xlsx')

    print(f"🔄 Traitement du fichier le plus récent : {latest_input}")
    transpose_and_flatten_partially(latest_input, output_file)



######################################################################################################################
#fichier 2
def clean_excel_columns2(file_path, output_path=None, header_rows=2):
    # Lire les premières lignes comme header (ex: 2 lignes)
    df = pd.read_excel(file_path, header=list(range(header_rows)))

    # Fusionner les lignes d'en-tête pour créer des noms de colonnes propres
    new_columns = []
    for col in df.columns:
        # col est une tuple ('Total Jeu au pied', 'Perte du ballon') par exemple
        clean_parts = [str(c).strip() for c in col if 'Unnamed' not in str(c) and str(c) != 'nan']
        if clean_parts:
            new_columns.append(" - ".join(clean_parts))
        else:
            new_columns.append("")
    
    df.columns = new_columns

    # Extraire la première colonne (généralement celle qui contient les noms des joueurs)
    col1 = df.iloc[:, 0]  # Prend toutes les lignes, colonne 0 (la première)
    col1 = col1.dropna()   # Supprime les lignes vides si besoin
    col1 = col1.iloc[1:]   # Enlève le premier élément (en-tête ou total)
    
    # Facultatif : nettoyer les valeurs vides
    col1 = col1.dropna().reset_index(drop=True)

    # 🔴 Supprimer les lignes contenant "Total stats"
    col1 = col1[~col1.astype(str).str.contains("Total stats", case=False)].reset_index(drop=True)

    # Nettoyer les noms : enlever le numéro et le point (ex: "1. OLIVIER Tristan" → "OLIVIER Tristan")
    col1 = col1.astype(str).apply(lambda x: re.sub(r'^\d+\.\s*', '', x))

    # Séparer les noms en Nom / Prénom
    noms = col1.str.extract(r'(?P<Nom>.+)\s+(?P<Prénom>\S+)$')

    # Créer une liste vide pour stocker les résultats sous forme longue
    long_data = []

    # Parcourir les joueurs et leurs statistiques pour chaque action
    for i, joueur in enumerate(col1.tolist()):
        nom = noms.loc[i, 'Nom']
        prenom = noms.loc[i, 'Prénom']
    
        # Extraire les valeurs de chaque joueur, ignore la première colonne (les joueurs)
        valeurs = df.loc[df.iloc[:, 0].astype(str).str.contains(joueur)].iloc[0, 1:].tolist()

        # Associer chaque valeur à une action correspondante
        for action, valeur in zip(df.columns[1:], valeurs):
        # Ajouter une ligne pour chaque joueur-action-valeur
            long_data.append({"Nom": nom, "Prénom": prenom, "Action": action, "Valeur": valeur})

    # Convertir la liste en DataFrame
    df_long = pd.DataFrame(long_data)

    # Remplir les valeurs manquantes dans la colonne C avec 0
    df_long['Valeur'] = df_long['Valeur'].fillna(0)  # Remplacer les NaN dans la colonne "Valeur" par 0

    # Définir le chemin du fichier de sortie si non fourni
    if output_path is None:
        # Créer un nom de fichier en ajoutant "_modifie" avant l'extension
        base_name = os.path.splitext(file_path)[0]  # Enlever l'extension
        output_path = f"{base_name}_modifie.xlsx"    # Ajouter "_modifie" au nom du fichier

    # Créer un fichier Excel avec plusieurs feuilles
    with pd.ExcelWriter(output_path, engine='openpyxl') as writer:
        # Sauvegarder la feuille "long" dans le fichier Excel
        df_long.to_excel(writer, sheet_name="Stats Long Format", index=False)

        # Si tu veux ajouter d'autres feuilles de ton DataFrame initial modifié, tu peux le faire ici
        # Par exemple, si tu veux ajouter une feuille pour les stats nettoyées dans le format initial:
        #df.to_excel(writer, sheet_name="Stats Initiales", index=False)

    print(f"Le fichier a été enregistré sous: {output_path}")
    return df_long

# === Exemple d'utilisation ===
import glob
import os

# Trouver tous les fichiers "Data Match*.xlsx" sauf ceux déjà modifiés
input_files2 = [f for f in glob.glob("uploads/Export Stats Match*.xlsx") if "_modifie" not in f]

if not input_files2:
    print("❌ Aucun nouveau fichier 'Export Stats Match*.xlsx' trouvé dans le dossier uploads/")
else:
    # Prendre uniquement le fichier le plus récemment modifié
    latest_input2 = max(input_files2, key=os.path.getmtime)
    output_file2 = latest_input2.replace('.xlsx', '_modifie.xlsx')

    print(f"🔄 Traitement du fichier le plus récent : {latest_input2}")
    clean_excel_columns2(latest_input2, output_file2)

######################################################################################################################
#fichier 3
def clean_excel_columns3(file_path, output_path=None):
    # Lire le fichier Excel sans en-tête
    df = pd.read_excel(file_path, header=None)

    # Supprimer la première ligne (inutile)
    df = df.iloc[1:].reset_index(drop=True)

    # Extraire les futurs noms de colonnes depuis la ligne 0 (colonnes 1 à 7)
    future_column_names = df.iloc[0, 1:8].tolist()

    # Ajouter colonnes personnalisées : joueur et période
    final_columns = ['Joueur', 'Période'] + future_column_names

    # Supprimer la ligne des titres
    df = df.iloc[1:].reset_index(drop=True)

    # Initialiser liste pour stocker les lignes nettoyées
    cleaned_data = []

    i = 0
    while i + 2 < len(df):
        player_row = df.iloc[i]
        match_row = df.iloc[i + 1]
        mi1_row = df.iloc[i + 2]

        # Vérifier que les lignes sont valides (pour éviter erreurs en fin de fichier)
        if pd.isna(player_row[0]) or pd.isna(match_row[0]) or pd.isna(mi1_row[0]):
            i += 3
            continue

        joueur = player_row[0]

        # Ajouter chaque période avec ses données
        cleaned_data.append(
            [joueur, match_row[0]] + match_row[1:8].tolist()
        )
        cleaned_data.append(
            [joueur, mi1_row[0]] + mi1_row[1:8].tolist()
        )
        
        # Vérifier s'il y a une 3e ligne pour mi-temps 2
        if i + 3 < len(df):
            mi2_row = df.iloc[i + 3]
            if not pd.isna(mi2_row[0]) and mi2_row[0] == 'Mi-temps 2':
                cleaned_data.append(
                    [joueur, mi2_row[0]] + mi2_row[1:8].tolist()
                )
                i += 4
            else:
                i += 3
        else:
            i += 3

    # Créer DataFrame final
    df_cleaned = pd.DataFrame(cleaned_data, columns=final_columns)

    # Séparer en mots
    df_cleaned['Nom'] = df_cleaned['Joueur'].apply(lambda x: ' '.join(str(x).split()[:-1]))
    df_cleaned['Prénom'] = df_cleaned['Joueur'].apply(lambda x: str(x).split()[-1])

    
    # Réorganiser les colonnes : Nom, Prénom, Période, ...
    df_cleaned = df_cleaned[['Nom', 'Prénom', 'Période'] + future_column_names]


    # Sauvegarde si demandée
    if output_path:
        df_cleaned.to_excel(output_path, index=False)

    return df_cleaned

import glob
import os

# Trouver tous les fichiers "Data Match*.xlsx" sauf ceux déjà modifiés
input_files3 = [f for f in glob.glob("uploads/Rapport GPS*.xlsx") if "_modifie" not in f]

if not input_files3:
    print("❌ Aucun nouveau fichier 'Rapport GPS*.xlsx' trouvé dans le dossier uploads/")
else:
    # Prendre uniquement le fichier le plus récemment modifié
    latest_input3 = max(input_files3, key=os.path.getmtime)
    output_file3 = latest_input3.replace('.xlsx', '_modifie.xlsx')

    print(f"🔄 Traitement du fichier le plus récent : {latest_input3}")
>>>>>>> 6e3e7308c9e0a3cfb3ee5e373b077ed04762fa58
    clean_excel_columns3(latest_input3, output_file3)