

import pandas as pd
import sys
from app import app, db  # On importe le app et db de app.py
import sys
sys.stdout.reconfigure(encoding='utf-8')


def safe_value(val):
    if pd.isna(val):
        return None
    return val

def import_excel_to_db(file_path):
    with app.app_context():  # Obligatoire pour que le contexte Flask soit actif
        # Lire le fichier Excel
        df = pd.read_excel(file_path, sheet_name=0)
        df = df.where(pd.notnull(df), None)
        # Utiliser la connexion SQLAlchemy en mode brut
        try:
            connection = db.engine.raw_connection()
            cursor = connection.cursor()
        except Exception as e:
            print(f"❌ Erreur de connexion à la base de données : {e}")
            sys.exit(1)

        # Toutes tes requêtes restent identiques (aucun changement ici°
        sql_possession_mt_1 = """INSERT INTO possession_mt_1 (possession_mt_1_beziers, possession_mt_1_equipe_adverse, possession_mt_1_total) VALUES (%s, %s, %s)"""
        sql_possession_mt_2 = """INSERT INTO possession_mt_2 (possession_mt_2_beziers, possession_mt_2_equipe_adverse, possession_mt_2_total) VALUES (%s, %s, %s)"""
        sql_temps_effectif = """INSERT INTO temps_effectif (temps_effectif_beziers, temps_effectif_equipe_adverse, temps_effectif_total) VALUES (%s, %s, %s)"""
        sql_match = """INSERT INTO `match` (date, competition, locaux, visiteurs, score_locaux, score_visiteurs, stade, lieu, arbitre, journee, id_possession_mt_1, id_possession_mt_2, id_temps_effectif) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)"""
        sql_score_asbh = """INSERT INTO score (essais, transformations, drops, drops_tentes, penalites, penalites_tentees, id_equipe, id_match) VALUES (%s, %s, %s, %s, %s, %s, %s, %s)"""
        sql_points = """INSERT INTO points (id_equipe, id_match, points_total, points_positifs, points_neutres, points_negatifs, actions) VALUES (%s, %s, %s, %s, %s, %s, %s)"""

        # Identifiant ASBH
        cursor.execute("SELECT id_equipe FROM equipe WHERE nom_equipe = 'ASBH'")
        result = cursor.fetchone()
        id_equipe_asbh = result[0] if result else None

        if id_equipe_asbh is None:
            raise ValueError("⚠️ L'équipe ASBH n'a pas été trouvée dans la table 'equipe'.")

        for _, row in df.iterrows():
            cursor.execute(sql_possession_mt_1, (row['possession_mt_1_beziers'], row['possession_mt_1_equipe_adverse'], row['possession_mt_1_total']))
            possession_mt_1_id = cursor.lastrowid

            cursor.execute(sql_possession_mt_2, (row['possession_mt_2_beziers'], row['possession_mt_2_equipe_adverse'], row['possession_mt_2_total']))
            possession_mt_2_id = cursor.lastrowid

            cursor.execute(sql_temps_effectif, (row['temps_effectif_beziers'], row['temps_effectif_equipe_adverse'], row['temps_effectif_total']))
            temps_effectif_id = cursor.lastrowid

            cursor.execute(sql_match, (row['date'], row['competition'], row['locaux'], row['visiteurs'], row['score_locaux'], row['score_visiteurs'], row['stade'], row['lieu'], row['arbitre'], row['journee'], possession_mt_1_id, possession_mt_2_id, temps_effectif_id))
            id_match = cursor.lastrowid

            points_df = pd.read_excel(file_path, sheet_name=2)
            points_df = points_df.where(pd.notnull(points_df), None)
            for _, point_row in points_df.iterrows():
                nom_equipe = point_row['équipe'].upper()
                cursor.execute("SELECT id_equipe FROM equipe WHERE nom_equipe = %s", (nom_equipe,))
                result = cursor.fetchone()
                if not result:
                    raise ValueError(f"⚠️ L'équipe '{nom_equipe}' n'existe pas en BDD.")
                id_equipe = result[0]

                cursor.execute(sql_points, (id_equipe, id_match, point_row['total'], point_row['positif'], point_row['neutre'], point_row['negatif'], point_row['action']))

            cursor.execute(sql_score_asbh, (row['essais_asbh'], row['transformations_asbh'], row['drops_asbh'], row['drop_tentes_asbh'], row['penalites_asbh'], row['penalites_tentees_asbh'], id_equipe_asbh, id_match))

            suffixes_possibles = [col.split('_')[1] for col in df.columns if col.startswith('essais_') and not col.endswith('asbh')]
            if not suffixes_possibles:
                raise ValueError("⚠️ Aucune équipe adverse détectée dans les colonnes.")
            suffixe_adverse = suffixes_possibles[0]
            nom_equipe_adverse = suffixe_adverse.upper()

            cursor.execute("SELECT id_equipe FROM equipe WHERE nom_equipe = %s", (nom_equipe_adverse,))
            result = cursor.fetchone()
            id_equipe_adverse = result[0] if result else None
            if id_equipe_adverse is None:
                raise ValueError(f"⚠️ L'équipe '{nom_equipe_adverse}' n'existe pas.")

            score_adverse = (
                row[f'essais_{suffixe_adverse}'],
                row[f'transformations_{suffixe_adverse}'],
                row[f'drops_{suffixe_adverse}'],
                row[f'drop_tentes_{suffixe_adverse}'],
                row[f'penalites_{suffixe_adverse}'],
                row[f'penalites_tentees_{suffixe_adverse}'],
                id_equipe_adverse,
                id_match
            )
            cursor.execute(sql_score_asbh, score_adverse)

        connection.commit()
        cursor.close()
        connection.close()


# Appel de la fonction (toujours possible en direct)
if __name__ == "__main__":
    try:
        file_path = "uploads/Data Match ASBH - CAR_modifie.xlsx"
        import_excel_to_db(file_path)
    except Exception as e:
        print(f"❌ Une erreur globale est survenue : {e}")

