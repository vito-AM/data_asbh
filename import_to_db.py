import pandas as pd
import sys
from app import app, db  
sys.stdout.reconfigure(encoding='utf-8')

def safe_get(row, key):
    return row[key] if key in row and pd.notna(row[key]) else None

def import_excel_to_db(file_path3, file_path2, file_path1):
    with app.app_context():
        df = pd.read_excel(file_path1, sheet_name=0)
        df = df.where(pd.notnull(df), None)

        try:
            connection = db.engine.raw_connection()
            cursor = connection.cursor()
        except Exception as e:
            print(f"❌ Erreur de connexion à la base de données : {e}")
            sys.exit(1)

        sql_possession_mt_1 = """INSERT INTO possession_mt_1 (
            possession_mt_1_beziers, possession_mt_1_equipe_adverse, possession_mt_1_total
        ) VALUES (%s, %s, %s)"""

        sql_possession_mt_2 = """INSERT INTO possession_mt_2 (
            possession_mt_2_beziers, possession_mt_2_equipe_adverse, possession_mt_2_total
        ) VALUES (%s, %s, %s)"""

        sql_temps_effectif = """INSERT INTO temps_effectif (
            temps_effectif_beziers, temps_effectif_equipe_adverse, temps_effectif_total
        ) VALUES (%s, %s, %s)"""

        sql_match = """INSERT INTO `match` (
            date, competition, locaux, visiteurs, score_locaux, score_visiteurs,
            stade, lieu, arbitre, journee,
            id_possession_mt_1, id_possession_mt_2, id_temps_effectif
        ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)"""

        sql_score_asbh = """INSERT INTO score (
            essais, transformations, drops, drops_tentes, penalites, penalites_tentees, id_equipe, id_match
        ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s)"""

        cursor.execute("SELECT id_equipe FROM equipe WHERE nom_equipe = 'ASBH'")
        result = cursor.fetchone()
        id_equipe_asbh = result[0] if result else None

        if id_equipe_asbh is None:
            raise ValueError("⚠️ L'équipe ASBH n'a pas été trouvée dans la table 'equipe'.")

        sql_points = """INSERT INTO points (
        id_equipe, id_match, points_total, points_positifs, points_neutres, points_negatifs, actions
        ) VALUES (%s, %s, %s, %s, %s, %s, %s)"""

        sql_localisation = """INSERT INTO localisation (
        id_equipe, id_match, action, portion_terrain, temps, valeur
        ) VALUES (%s, %s, %s, %s, %s, %s)"""

        sql_fin_actions = """INSERT INTO fin_actions_collectives (
        total, mt1, mt2, id_equipe, action
        ) VALUES (%s, %s, %s, %s, %s)"""

        for _, row in df.iterrows():
            cursor.execute(sql_possession_mt_1, (
                safe_get(row, 'possession_mt_1_beziers'),
                safe_get(row, 'possession_mt_1_equipe_adverse'),
                safe_get(row, 'possession_mt_1_total')
            ))
            possession_mt_1_id = cursor.lastrowid

            cursor.execute(sql_possession_mt_2, (
                safe_get(row, 'possession_mt_2_beziers'),
                safe_get(row, 'possession_mt_2_equipe_adverse'),
                safe_get(row, 'possession_mt_2_total')
            ))
            possession_mt_2_id = cursor.lastrowid

            cursor.execute(sql_temps_effectif, (
                safe_get(row, 'temps_effectif_beziers'),
                safe_get(row, 'temps_effectif_equipe_adverse'),
                safe_get(row, 'temps_effectif_total')
            ))
            temps_effectif_id = cursor.lastrowid

            cursor.execute(sql_match, (
                safe_get(row, 'date'),
                safe_get(row, 'competition'),
                safe_get(row, 'locaux'),    
                safe_get(row, 'visiteurs'),
                safe_get(row, 'score_locaux'),
                safe_get(row, 'score_visiteurs'),
                safe_get(row, 'stade'),
                safe_get(row, 'lieu'),
                safe_get(row, 'arbitre'),
                safe_get(row, 'journee'),
                possession_mt_1_id,
                possession_mt_2_id,
                temps_effectif_id
            ))
            id_match = cursor.lastrowid

            gps_df = pd.read_excel(file_path3)

            

            sql_insert_courir = """
            INSERT INTO courir (
                id_joueur, id_match, periode, temps_de_jeu, distance_totale, min, marche, intensite, vmax, nb_accel
            ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """

            for _, gps_row in gps_df.iterrows():
                nom = gps_row['Nom'].strip().upper()
                prenom = gps_row['Prénom'].strip()

                cursor.execute("""
                    SELECT id_joueur FROM joueur
                    WHERE UPPER(`nom_joueur`) = %s AND prenom_joueur = %s
                """, (nom, prenom))
                result = cursor.fetchone()

                if not result:
                    raise ValueError(f"❌ Joueur introuvable dans 'joueur' : {nom} {prenom}")

                id_joueur = result[0]

                cursor.execute(sql_insert_courir, (
                    id_joueur,
                    id_match,
                    gps_row['Période'],
                    gps_row['Tps jeu (min)'],
                    gps_row['Dist. Tot. (m)'],
                    gps_row['m/min'],
                    gps_row['% marche'],
                    gps_row['% intensité'],
                    gps_row['Vmax (km/h)'],
                    gps_row['Nb accel']
                ))

            points_df = pd.read_excel(file_path1, sheet_name=2)

            for _, point_row in points_df.iterrows():
                nom_equipe = point_row['équipe'].upper()

                cursor.execute("SELECT id_equipe FROM equipe WHERE nom_equipe = %s", (nom_equipe,))
                result = cursor.fetchone()
                if not result:
                    raise ValueError(f"⚠️ L'équipe '{nom_equipe}' n'existe pas en BDD.")
                id_equipe = result[0]

                cursor.execute(sql_points, (
                    id_equipe,
                    id_match,
                    point_row['total'],
                    point_row['positif'],
                    point_row['neutre'],
                    point_row['negatif'],
                    point_row['action']
                ))

            cursor.execute(sql_score_asbh, (
                safe_get(row, 'essais_asbh'), safe_get(row, 'transformations_asbh'), safe_get(row, 'drops_asbh'),
                safe_get(row, 'drop_tentes_asbh'), safe_get(row, 'penalites_asbh'), safe_get(row, 'penalites_tentees_asbh'),
                id_equipe_asbh, id_match
            ))

            suffixes_possibles = [col.split('_')[1] for col in df.columns if col.startswith('essais_') and not col.endswith('asbh')]
            if not suffixes_possibles:
                raise ValueError("⚠️ Aucune équipe adverse détectée dans les colonnes (ex: essais_car).")
            suffixe_adverse = suffixes_possibles[0]
            nom_equipe_adverse = suffixe_adverse.upper()

            cursor.execute("SELECT id_equipe FROM equipe WHERE nom_equipe = %s", (nom_equipe_adverse,))
            result = cursor.fetchone()
            id_equipe_adverse = result[0] if result else None

            if id_equipe_adverse is None:
                raise ValueError(f"⚠️ L'équipe '{nom_equipe_adverse}' n'existe pas dans la table 'equipe'.")

            cursor.execute(sql_score_asbh, (
                safe_get(row, f'essais_{suffixe_adverse}'),
                safe_get(row, f'transformations_{suffixe_adverse}'),
                safe_get(row, f'drops_{suffixe_adverse}'),
                safe_get(row, f'drop_tentes_{suffixe_adverse}'),
                safe_get(row, f'penalites_{suffixe_adverse}'),
                safe_get(row, f'penalites_tentees_{suffixe_adverse}'),
                id_equipe_adverse,
                id_match
            ))

            localisation_df = pd.read_excel(file_path1, sheet_name=4)

            for _, loc_row in localisation_df.iterrows():
                nom_equipe_localisation = loc_row['equipe'].upper()
                cursor.execute("SELECT id_equipe FROM equipe WHERE nom_equipe = %s", (nom_equipe_localisation,))
                result = cursor.fetchone()
                if not result:
                    raise ValueError(f"⚠️ L'équipe '{nom_equipe_localisation}' n'existe pas en BDD.")
                id_equipe_localisation = result[0]

                cursor.execute(sql_localisation, (
                    id_equipe_localisation,
                    id_match,
                    loc_row['action'],
                    loc_row['portion_terrain'],
                    loc_row['temps'],
                    loc_row['valeur']
                ))

            fin_actions_df = pd.read_excel(file_path1, sheet_name=3)

            for _, fa_row in fin_actions_df.iterrows():
                nom_equipe_fin_action = fa_row['équipe'].upper()
                cursor.execute("SELECT id_equipe FROM equipe WHERE nom_equipe = %s", (nom_equipe_fin_action,))
                result = cursor.fetchone()
                if not result:
                    raise ValueError(f"⚠️ L'équipe '{nom_equipe_fin_action}' n'existe pas en BDD.")
                id_equipe_fin_action = result[0]

                cursor.execute(sql_fin_actions, (
                    fa_row['Total'],
                    fa_row['MT1'],
                    fa_row['MT2'],
                    id_equipe_fin_action,
                    fa_row['action']
                ))

            stats_df = pd.read_excel(file_path2, sheet_name="Stats Long Format")

            sql_insert_stat = """
            INSERT INTO export_stat_match (action, valeur, id_joueur)
            VALUES (%s, %s, %s)
            """
            
            for _, row_stat in stats_df.iterrows():
                nom = row_stat['Nom'].upper().strip()
                prenom = row_stat['Prénom'].strip()

                cursor.execute("""
                SELECT id_joueur FROM joueur
                WHERE UPPER(nom_joueur) = %s AND prenom_joueur = %s
                """, (nom, prenom))
                result = cursor.fetchone()

                if not result:
                    raise ValueError(f"❌ Joueur introuvable : {nom} {prenom}")

                id_joueur = result[0]

                cursor.execute(sql_insert_stat, (
                    row_stat['Action'],
                    row_stat['Valeur'],
                    id_joueur
                ))

    connection.commit()
    cursor.close()
    connection.close()
    print("✅ Données importées avec succès.")


import glob
import os

# Chercher tous les fichiers *_modifie.xlsx dans le dossier courant
all_files = glob.glob("uploads/*_modifie.xlsx")

if len(all_files) < 3:
    print("❌ Moins de 3 fichiers '_modifie.xlsx' trouvés. Vérifiez les fichiers disponibles.")
else:
    # Trier les fichiers par date de modification (du plus récent au plus ancien)
    all_files.sort(key=os.path.getmtime, reverse=True)

    # Prendre les 3 plus récents
    file_path1 = all_files[0]  # Data Match
    file_path2 = all_files[1]  # Export Stats Match
    file_path3 = all_files[2]  # Rapport GPS

    print(f"📥 Fichiers sélectionnés :\n 1️⃣ {file_path1}\n 2️⃣ {file_path2}\n 3️⃣ {file_path3}")
    import_excel_to_db(file_path1, file_path2, file_path3)
