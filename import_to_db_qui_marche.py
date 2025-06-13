import pandas as pd
import sys
import glob
import os
import traceback
from app import app, db

# ──────────────────────────────────────────────────────────────────────────────
#  Utilitaires
# ──────────────────────────────────────────────────────────────────────────────

# Assure un encodage UTF‑8 dans la console Docker/WSL/Windows
sys.stdout.reconfigure(encoding="utf-8")

def safe_get(row: pd.Series, key: str):
    """Renvoie row[key] ou None si la cellule est vide / absente."""
    return row[key] if key in row and pd.notna(row[key]) else None


def to_seconds(value):
    """Transforme une valeur HH:MM:SS, MM:SS ou numérique en nombre de secondes."""
    if value is None:
        return None

    # Format texte « HH:MM:SS » ou « MM:SS »
    if isinstance(value, str) and ":" in value:
        parts = value.split(":")
        try:
            if len(parts) == 3:
                h, m, s = map(int, parts)
            elif len(parts) == 2:
                h = 0
                m, s = map(int, parts)
            else:
                return None
            return h * 3600 + m * 60 + s
        except ValueError:
            return None

    # Nombre déjà en secondes
    if isinstance(value, (int, float)):
        return int(value)

    return None


# ──────────────────────────────────────────────────────────────────────────────
#  Import principal
# ──────────────────────────────────────────────────────────────────────────────

def import_excel_to_db(data_match_path: str, stats_path: str, gps_path: str):
    """Importe les 3 fichiers XLSX dans la base MySQL en évitant les doublons."""

    with app.app_context():
        db.engine.echo = True  # SQL visible dans la console
        conn = db.engine.raw_connection()
        cursor = conn.cursor()

        try:
            # ───────────────
            # 1) Chargements uniques (feuilles satellites)
            # ───────────────
            gps_df   = pd.read_excel(gps_path)
            pts_df   = pd.read_excel(data_match_path, sheet_name=2)
            loc_df   = pd.read_excel(data_match_path, sheet_name=4)
            fa_df    = pd.read_excel(data_match_path, sheet_name=3)
            stats_df = pd.read_excel(stats_path,  sheet_name="Stats Long Format")

            # Nom de l'équipe visiteuse (≠ ASBH)
            equipes = fa_df["équipe"].astype(str).str.strip().str.upper().unique().tolist()
            opp = [e for e in equipes if e != "ASBH"]
            if len(opp) != 1:
                raise ValueError(f"⚠️ Impossible de déterminer une seule équipe adverse ({equipes})")
            visiteurs_name = opp[0]

            # ───────────────
            # 2) Feuille principale « Data Match »
            # ───────────────
            base_df = pd.read_excel(data_match_path, sheet_name=0)
            df      = base_df.dropna(how="all").reset_index(drop=True)  # retire les lignes 100 % vides

            if df.empty:
                raise ValueError("Feuille 0 vide après filtrage.")

            # ───────────────
            # 3) Pré‑compilation des requêtes SQL
            # ───────────────
            sql_match = """
                INSERT INTO `match` (
                  date, competition, locaux, visiteurs,
                  score_locaux, score_visiteurs,
                  stade, lieu, arbitre, journee,
                  possession_mt_1_total, possession_mt_2_total, temps_effectif_total
                ) VALUES (
                  %s,%s,%s,%s,%s,%s,%s,%s,%s,%s,
                  SEC_TO_TIME(%s),SEC_TO_TIME(%s),SEC_TO_TIME(%s)
                )
            """

            sql_pos1 = "INSERT INTO possession_mt_1 (possession_mt_1_e,id_equipe,id_match) VALUES (SEC_TO_TIME(%s),%s,%s)"
            sql_pos2 = "INSERT INTO possession_mt_2 (possession_mt_2_e,id_equipe,id_match) VALUES (SEC_TO_TIME(%s),%s,%s)"
            sql_temps = "INSERT INTO temps_effectif (temps_effectif_e,id_equipe,id_match) VALUES (SEC_TO_TIME(%s),%s,%s)"

            sql_score = """
                INSERT INTO score (
                  essais, transformations, drops, drops_tentes,
                  penalites, penalites_tentees, id_equipe, id_match
                ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s)
            """

            sql_points = """
                INSERT INTO points (
                  id_equipe, id_match,
                  points_total, points_positifs,
                  points_neutres, points_negatifs, actions
                ) VALUES (%s,%s,%s,%s,%s,%s,%s)
            """

            sql_loc = "INSERT INTO localisation (id_equipe,id_match,action,portion_terrain,temps,valeur) VALUES (%s,%s,%s,%s,%s,%s)"

            sql_fin = """
                INSERT INTO fin_actions_collectives (
                  total, mt1, mt2, id_equipe, id_match, action
                ) VALUES (%s,%s,%s,%s,%s,%s)
            """

            sql_courir = """
                INSERT INTO courir (
                  id_joueur,id_match,periode,
                  temps_de_jeu,distance_totale,
                  min,marche,intensite,
                  vmax,nb_accel
                ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
            """

            sql_stat = "INSERT INTO export_stat_match (action,valeur,id_joueur,id_match) VALUES (%s,%s,%s,%s)"

            # ───────────────
            # 4) Identifiants des équipes
            # ───────────────
            cursor.execute("SELECT id_equipe FROM equipe WHERE nom_equipe='ASBH'")
            r = cursor.fetchone()
            if not r:
                raise ValueError("⚠️ L'équipe ASBH introuvable dans la table `equipe`.")
            id_asbh = r[0]

            cursor.execute("SELECT id_equipe FROM equipe WHERE nom_equipe=%s", (visiteurs_name,))
            id_adv = cursor.fetchone()
            if not id_adv:
                raise ValueError(f"⚠️ L'équipe {visiteurs_name} introuvable dans la table `equipe`. ")
            id_adv = id_adv[0]

            # ───────────────
            # 5) Traitement ligne par ligne (une ligne = un match)
            # ───────────────
            for _, row in df.iterrows():
                # a) Insertion du match
                tot1 = to_seconds(safe_get(row, "possession_mt_1_total"))
                tot2 = to_seconds(safe_get(row, "possession_mt_2_total"))
                ttot = to_seconds(safe_get(row, "temps_effectif_total"))

                cursor.execute(sql_match, (
                    safe_get(row, "date"), safe_get(row, "competition"),
                    safe_get(row, "locaux"), visiteurs_name,
                    safe_get(row, "score_locaux"), safe_get(row, "score_visiteurs"),
                    safe_get(row, "stade"), safe_get(row, "lieu"),
                    safe_get(row, "arbitre"), safe_get(row, "journee"),
                    tot1, tot2, ttot,
                ))
                id_match = cursor.lastrowid

                # b) Possession & temps effectif (détail par équipe)
                for prefix, sql in (
                    ("possession_mt_1", sql_pos1),
                    ("possession_mt_2", sql_pos2),
                    ("temps_effectif",  sql_temps),
                ):
                    bzr_seconds = to_seconds(safe_get(row, f"{prefix}_beziers"))
                    adv_seconds = to_seconds(safe_get(row, f"{prefix}_equipe_adverse"))

                    cursor.execute(sql, (bzr_seconds, id_asbh, id_match))
                    cursor.execute(sql, (adv_seconds, id_adv,  id_match))

                # c) Scores (ASBH puis visiteurs)
                cursor.execute(sql_score, (
                    safe_get(row, "essais_asbh"),
                    safe_get(row, "transformations_asbh"),
                    safe_get(row, "drops_asbh"),
                    safe_get(row, "drop_tentes_asbh"),
                    safe_get(row, "penalites_asbh"),
                    safe_get(row, "penalites_tentees_asbh"),
                    id_asbh, id_match,
                ))

                cursor.execute(sql_score, (
                    safe_get(row, f"essais_{visiteurs_name.lower()}"),
                    safe_get(row, f"transformations_{visiteurs_name.lower()}"),
                    safe_get(row, f"drops_{visiteurs_name.lower()}"),
                    safe_get(row, f"drop_tentes_{visiteurs_name.lower()}"),
                    safe_get(row, f"penalites_{visiteurs_name.lower()}"),
                    safe_get(row, f"penalites_tentees_{visiteurs_name.lower()}"),
                    id_adv, id_match,
                ))

                # d) GPS (table "courir")
                for _, gps in gps_df.iterrows():
                    nom    = str(gps["Nom"]).strip().upper()
                    prenom = str(gps["Prénom"]).strip()
                    cursor.execute(
                        "SELECT id_joueur FROM joueur WHERE UPPER(nom_joueur)=%s AND prenom_joueur=%s",
                        (nom, prenom),
                    )
                    rj = cursor.fetchone()
                    if not rj:
                        print(f"⚠️ GPS joueur introuvable : {nom} {prenom}")
                        continue

                    cursor.execute(sql_courir, (
                        rj[0], id_match,
                        gps["Période"], gps["Tps jeu (min)"], gps["Dist. Tot. (m)"],
                        gps["m/min"], gps["% marche"], gps["% intensité"],
                        gps["Vmax (km/h)"], gps["Nb accel"],
                    ))

                # e) Points par action (table "points")
                for _, pt in pts_df.iterrows():
                    eq = str(pt["équipe"]).strip().upper()
                    cursor.execute("SELECT id_equipe FROM equipe WHERE nom_equipe=%s", (eq,))
                    rpt = cursor.fetchone()
                    if not rpt:
                        print(f"⚠️ Équipe points introuvable : {eq}")
                        continue

                    cursor.execute(sql_points, (
                        rpt[0], id_match,
                        pt["total"], pt["positif"], pt["neutre"], pt["negatif"], pt["action"],
                    ))

                # f) Localisation
                for _, loc in loc_df.iterrows():
                    eq = str(loc["equipe"]).strip().upper()
                    cursor.execute("SELECT id_equipe FROM equipe WHERE nom_equipe=%s", (eq,))
                    rloc = cursor.fetchone()
                    if not rloc:
                        print(f"⚠️ Localisation équipe introuvable : {eq}")
                        continue

                    cursor.execute(sql_loc, (
                        rloc[0], id_match,
                        loc["action"], loc["portion_terrain"],
                        loc["temps"], loc["valeur"],
                    ))

                # g) Fin des actions collectives
                for _, fa in fa_df.iterrows():
                    eq = str(fa["équipe"]).strip().upper()
                    cursor.execute("SELECT id_equipe FROM equipe WHERE nom_equipe=%s", (eq,))
                    rfin = cursor.fetchone()
                    if not rfin:
                        print(f"⚠️ Fin action équipe introuvable : {eq}")
                        continue

                    cursor.execute(sql_fin, (
                        fa["Total"], fa["MT1"], fa["MT2"],
                        rfin[0], id_match, fa["action"],
                    ))

                # h) Stats individuelles longue forme
                inserted = 0
                for _, st in stats_df.iterrows():
                    nom    = str(st["Nom"]).strip().upper()
                    prenom = str(st["Prénom"]).strip()

                    # Recherche exacte (nom + prénom)
                    cursor.execute(
                        "SELECT id_joueur FROM joueur WHERE UPPER(nom_joueur)=%s AND prenom_joueur=%s",
                        (nom, prenom),
                    )
                    res = cursor.fetchone()

                    # 1ère lettre du prénom (ex: "J.")
                    if not res and len(prenom) <= 2 and "." in prenom:
                        initial = prenom.replace(".", "")
                        cursor.execute(
                            "SELECT id_joueur FROM joueur WHERE UPPER(nom_joueur)=%s AND LEFT(prenom_joueur,1)=%s",
                            (nom, initial),
                        )
                        res = cursor.fetchone()

                    # Plusieurs homonymes possibles
                    if not res:
                        cursor.execute(
                            "SELECT id_joueur,prenom_joueur FROM joueur WHERE UPPER(nom_joueur)=%s",
                            (nom,),
                        )
                        rows = cursor.fetchall()
                        if len(rows) == 1:
                            res = (rows[0][0],)
                        elif len(rows) > 1:
                            print(f"⚠️ Plusieurs joueurs pour {nom}: {[r[1] for r in rows]}")
                            res = (rows[0][0],)

                    if not res:
                        print(f"❌ Joueur stat introuvable: {nom} {prenom}")
                        continue

                    action = st.get("Action") or st.get("action")
                    valeur = st.get("Valeur") or st.get("valeur")
                    if valeur is None:
                        print(f"⚠️ Valeur manquante pour {nom} {prenom}")
                        continue

                    cursor.execute(sql_stat, (action, valeur, res[0], id_match))
                    inserted += 1

                print(f"DEBUG: {inserted} stats importées pour le match {id_match}")

            # ───────────────
            # 6) Validation de la transaction
            # ───────────────
            conn.commit()
            print("✅ Import terminé avec succès.")

        except Exception as e:
            print("❌ Erreur pendant l'import:", e)
            traceback.print_exc()
            conn.rollback()
            raise

        finally:
            cursor.close()
            conn.close()


# ──────────────────────────────────────────────────────────────────────────────
#  Entrée CLI
# ──────────────────────────────────────────────────────────────────────────────

if __name__ == "__main__":
    files = glob.glob("uploads/*_modifie.xlsx")

    if len(files) < 3:
        print("❌ Moins de 3 fichiers '_modifie.xlsx' trouvés.")
        sys.exit(1)

    files.sort(key=os.path.getmtime, reverse=True)  # plus récent d'abord

    try:
        data = next(f for f in files if "Data Match"   in os.path.basename(f))
        stats = next(f for f in files if "Export Stats" in os.path.basename(f))
        gps   = next(f for f in files if "Rapport GPS"  in os.path.basename(f))
    except StopIteration:
        print("❌ Impossible d'apparier les 3 fichiers (_Data Match_, _Export Stats_, _Rapport GPS_).")
        sys.exit(1)

    print(f"📥 Data Match  = {data}\n📥 Stats Match = {stats}\n📥 GPS        = {gps}")

    try:
        import_excel_to_db(data, stats, gps)
    except Exception:
        sys.exit(1)
