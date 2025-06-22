import pandas as pd
import json
import sys
import glob
import os
import traceback
from app import app, db

import unicodedata

MISSING_PLAYERS = set()          # {("NOM", "Prénom")}


def normalize_name(s: str) -> str:
    """Retire accents, points et met en majuscules."""
    if not isinstance(s, str):
        return ""
    # décompose les accents
    nfkd = unicodedata.normalize('NFKD', s)
    # conserve que les caractères ASCII
    ascii_bytes = nfkd.encode('ASCII', 'ignore')
    clean = ascii_bytes.decode('ASCII')
    # supprime les points, met en majuscules et strippe
    return clean.replace(".", "").upper().strip()


# ──────────────────────────────────────────────────────────────────────────────
#  Utilitaires
# ──────────────────────────────────────────────────────────────────────────────
PONDERATIONS = {
    "1":  {"attaque": 0.20, "defense": 0.25, "spec": 0.25, "engagement": 0.15, "discipline": 0.10, "initiative": 0.05},
    "2":  {"attaque": 0.25, "defense": 0.20, "spec": 0.20, "engagement": 0.15, "discipline": 0.10, "initiative": 0.10},
    "3":  {"attaque": 0.10, "defense": 0.25, "spec": 0.35, "engagement": 0.15, "discipline": 0.10, "initiative": 0.05},
    "4":  {"attaque": 0.20, "defense": 0.25, "spec": 0.20, "engagement": 0.15, "discipline": 0.15, "initiative": 0.05},
    "5":  {"attaque": 0.20, "defense": 0.25, "spec": 0.15, "engagement": 0.15, "discipline": 0.15, "initiative": 0.10},
    "6":  {"attaque": 0.25, "defense": 0.25, "spec": 0.15, "engagement": 0.20, "discipline": 0.10, "initiative": 0.05},
    "7":  {"attaque": 0.10, "defense": 0.30, "spec": 0.25, "engagement": 0.20, "discipline": 0.10, "initiative": 0.15},
    "8":  {"attaque": 0.25, "defense": 0.20, "spec": 0.25, "engagement": 0.20, "discipline": 0.10, "initiative": 0.10},
    "9":  {"attaque": 0.20, "defense": 0.20, "spec": 0.30, "engagement": 0.10, "discipline": 0.10, "initiative": 0.10},
    "10": {"attaque": 0.25, "defense": 0.20, "spec": 0.20, "engagement": 0.10, "discipline": 0.15, "initiative": 0.10},
    "11": {"attaque": 0.35, "defense": 0.20, "spec": 0.10, "engagement": 0.15, "discipline": 0.15, "initiative": 0.05},
    "12": {"attaque": 0.25, "defense": 0.25, "spec": 0.15, "engagement": 0.20, "discipline": 0.10, "initiative": 0.05},
    "13": {"attaque": 0.30, "defense": 0.30, "spec": 0.20, "engagement": 0.10, "discipline": 0.05, "initiative": 0.05},
    "14": {"attaque": 0.35, "defense": 0.20, "spec": 0.10, "engagement": 0.15, "discipline": 0.15, "initiative": 0.05},
    "15": {"attaque": 0.20, "defense": 0.15, "spec": 0.35, "engagement": 0.10, "discipline": 0.10, "initiative": 0.10}
}

# Barème de contribution par action
COEFFICIENTS = {
        "Jeu au pied - Perte du ballon": ("defense", -5),
        "Jeu au pied - Sortie du ballon": ("spec", 10),
        "Jeu au pied - Injouable (perte)": ("defense", -3),
        "Jeu au pied - Positif": ("spec", -3),
        "Jeu au pied - Négatif": ("engagement", -4),
        "Perte de balle - Passe manquée": ("discipline", -9),
        "Perte de balle - En avant": ("discipline", -8),
        "Perte de balle - Perte du ballon": ("defense", -2),
        "Perte de balle - Négatif": ("defense", -1),
        "Faute règlement - Jeu au sol": ("discipline", -3),
        "Faute règlement - Hors jeu de ligne": ("discipline", -5),
        "Faute règlement - Brutalité": ("discipline", -6),
        "Faute règlement - Pénalité": ("discipline", -10),
        "Faute règlement - CPP Concédé": ("discipline", -9),
        "Faute règlement - Négatif": ("discipline", -7),
        "Faute technique - Touche": ("discipline", -5),
        "Faute technique - Jeu Courant": ("discipline", -4),
        "Faute technique - En avant": ("discipline", -6),
        "Faute technique - Mauvaise Passe": ("discipline", -5),
        "Faute technique - Avantage": ("discipline", -3),
        "Faute technique - Mêlée": ("discipline", -6),
        "Faute technique - Conservation": ("discipline", -7),
        "Faute technique - CPF Concédé": ("discipline", -8),
        "Faute technique - Négatif": ("discipline", -6),
        "Points - Essai": ("attaque", 9),
        "Points - Transformation": ("attaque", 6),
        "Points - Sortie du ballon": ("attaque", 3),
        "Points - Marque": ("attaque", 10),
        "Points - Positif": ("attaque", 5),
        "Points - Négatif": ("attaque", -5),
        "Plaquage - Récupérateur": ("defense", 6),
        "Plaquage - Perte du ballon": ("defense", -4),
        "Plaquage - CPP Concédé": ("defense", -8),
        "Plaquage - Mêlée concédée": ("defense", -6),
        "Plaquage - Sortie du ballon": ("defense", 2),
        "Plaquage - Positif": ("defense", 3),
        "Plaquage - Négatif": ("defense", -10),
        "Plaquage manqué - Négatif": ("defense", -5),
        "Franchissement - Marque": ("attaque", 8),
        "Franchissement - Positif": ("attaque", 5),
        "Récupération - Positif": ("defense", 4),
        "Assistant - Positif": ("engagement", 3),
        "Lanceur - Conservation": ("spec", 4),
        "Lanceur - Perte du ballon": ("spec", -4),
        "Lanceur - Mêlée concédée": ("spec", -5),
        "Lanceur - CPF Concédé": ("spec", -6),
        "Lanceur - Positif": ("spec", 5),
        "Lanceur - Négatif": ("spec", -5),
        "Sauteur - Conservation": ("spec", 4),
        "Sauteur - Perte du ballon": ("spec", -4),
        "Sauteur - Mêlée concédée": ("spec", -6),
        "Sauteur - CPF Concédé": ("spec", -5),
        "Sauteur - Positif": ("spec", 5),
        "Sauteur - Neutre": ("spec", 0),
        "Sauteur - Négatif": ("spec", -5),
        "Contreur - Perte du ballon": ("spec", -4),
        "Contreur - Positif": ("spec", 6),
        "Pousseur - Conservation": ("spec", 5),
        "Pousseur - CPP Concédé": ("spec", -8),
        "Pousseur - CPP Obtenu": ("spec", 7),
        "Pousseur - CPF Obtenu": ("spec", 7),
        "Pousseur - Injouable": ("spec", 2),
        "Pousseur - Positif": ("spec", 5),
        "Pousseur - Neutre": ("spec", 0),
        "Pousseur - Négatif": ("spec", -5),
        "Soutien Off - Conservation": ("engagement", 3),
        "Soutien Off - Perte du ballon": ("engagement", -4),
        "Soutien Off - CPP Concédé": ("engagement", -5),
        "Soutien Off - CPP Obtenu": ("engagement", 4),
        "Soutien Off - Positif": ("engagement", 3),
        "Soutien Off - Neutre": ("engagement", 0),
        "Soutien Off - Négatif": ("engagement", -3),
        "Porteur de balle - Conservation": ("engagement", 4),
        "Porteur de balle - Perte du ballon": ("engagement", -5),
        "Porteur de balle - CPP Concédé": ("engagement", -6),
        "Porteur de balle - CPP Obtenu": ("engagement", 5),
        "Porteur de balle - Positif": ("engagement", 4),
        "Porteur de balle - Neutre": ("engagement", 0),
        "Porteur de balle - Négatif": ("engagement", -4),
        "Passeur - Conservation": ("engagement", 3),
        "Passeur - CPP Obtenu": ("engagement", 4),
        "Passeur - Positif": ("engagement", 3),
        "Contest Air - Gagne": ("defense", 6),
        "Contest Air - Perdu": ("defense", -4),
        "Contest Air - Conservation": ("defense", 3),
        "Contest Air - Perte du ballon": ("defense", -3),
        "Contest Air - Positif": ("defense", 4),
        "Contest Air - Négatif": ("defense", -4),
        "botteur - Conservation": ("spec", 3),
        "botteur - Perte du ballon": ("spec", -4),
        "botteur - Positif": ("spec", 4)
}

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
    with app.app_context():
        db.engine.echo = True
        conn = db.engine.raw_connection()
        cur = conn.cursor()
        try:
            # 1) Chargements
            gps_df   = pd.read_excel(gps_path)
            pts_df   = pd.read_excel(data_match_path, sheet_name=2)
            loc_df   = pd.read_excel(data_match_path, sheet_name=4)
            fa_df    = pd.read_excel(data_match_path, sheet_name=3)
            stats_df = pd.read_excel(stats_path,  sheet_name="Stats Long Format")
            base_df  = (
                pd.read_excel(data_match_path, sheet_name=0)
                .dropna(how="all")
                .reset_index(drop=True)
            )
            effectif_df = (
            pd.read_excel(data_match_path, sheet_name=1)
            .dropna(subset=["nom", "prenom", "poste"])
            .assign(
                nom=lambda d: d["nom"].str.strip().str.upper(),
                prenom=lambda d: d["prenom"].str.strip(),
                poste=lambda d: d["poste"].astype(str).str.strip()
            )
            # ⬇︎  on garde seulement les postes 1-15
            .loc[lambda d: d["poste"].isin(PONDERATIONS.keys())]
            .reset_index(drop=True)
        )


            # 2) Équipes (depuis la seule feuille référence)
            equipes = (
                fa_df["équipe"].astype(str)
                .str.strip().str.upper().unique().tolist()
            )
            if len(equipes) != 2:
                raise ValueError(f"Deux équipes attendues, trouvé : {equipes}")

            locaux_nom, visiteurs_nom = equipes   # ordre FA = vérité terrain

            def get_or_create_team(nom):
                cur.execute("SELECT id_equipe FROM equipe WHERE nom_equipe=%s", (nom,))
                r = cur.fetchone()
                if r:
                    return r[0]
                cur.execute("INSERT INTO equipe (nom_equipe) VALUES (%s)", (nom,))
                print(f"➕  Nouvelle équipe : « {nom} »")
                return cur.lastrowid

            TEAM_ID = {nom: get_or_create_team(nom) for nom in equipes}
            # Dictionnaire nom normalisé --> nom canonique
            TEAM_NORM = {normalize_name(n): n for n in equipes} 
            id_locaux, id_visiteurs = TEAM_ID[locaux_nom], TEAM_ID[visiteurs_nom]

            # ─────────────────────────────────────────────────────────
            # 3) Pré-compilation des INSERT (identiques à la version préc.)
            # ─────────────────────────────────────────────────────────
            sql_match = """
                INSERT INTO `match` (
                  date, competition, locaux, visiteurs, score_locaux, score_visiteurs,
                  stade, lieu, arbitre, journee,
                  possession_mt_1_total, possession_mt_2_total, temps_effectif_total
                ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,SEC_TO_TIME(%s),SEC_TO_TIME(%s),SEC_TO_TIME(%s))
            """
            sql_pos1  = "INSERT INTO possession_mt_1 (possession_mt_1_e,id_equipe,id_match) VALUES (SEC_TO_TIME(%s),%s,%s)"
            sql_pos2  = "INSERT INTO possession_mt_2 (possession_mt_2_e,id_equipe,id_match) VALUES (SEC_TO_TIME(%s),%s,%s)"
            sql_temps = "INSERT INTO temps_effectif  (temps_effectif_e ,id_equipe,id_match) VALUES (SEC_TO_TIME(%s),%s,%s)"
            sql_score = """
                INSERT INTO score (
                  essais, transformations, drops, drops_tentes,
                  penalites, penalites_tentees, id_equipe, id_match
                ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s)
            """
            sql_points = """
                INSERT INTO points (
                  id_equipe,id_match,points_total,points_positifs,
                  points_neutres,points_negatifs,actions
                ) VALUES (%s,%s,%s,%s,%s,%s,%s)
            """
            sql_loc   = "INSERT INTO localisation (id_equipe,id_match,action,portion_terrain,temps,valeur) VALUES (%s,%s,%s,%s,%s,%s)"
            sql_fin   = """
                INSERT INTO fin_actions_collectives (total,mt1,mt2,id_equipe,id_match,action)
                VALUES (%s,%s,%s,%s,%s,%s)
            """
            sql_courir = """
                INSERT INTO courir (
                  id_joueur,id_match,periode,temps_de_jeu,distance_totale,
                  min,marche,intensite,vmax,nb_accel
                ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
            """
            sql_stat = "INSERT INTO export_stat_match (action,valeur,id_joueur,id_match) VALUES (%s,%s,%s,%s)"

            # 4) Pré-chargement des joueurs (table joueur → id)
            cur.execute("SELECT id_joueur, nom_joueur, prenom_joueur FROM joueur")
            joueurs_table = {
                (normalize_name(n), normalize_name(p)): idj
                for idj, n, p in cur.fetchall()
            }

            # 5) On ne traite qu’UN seul match par import → première ligne
            if base_df.empty:
                raise ValueError("Feuille Data Match vide.")
            row = base_df.iloc[0]

            # a) INSERT MATCH
            cur.execute(sql_match, (
                safe_get(row,"date"), safe_get(row,"competition"),
                locaux_nom, visiteurs_nom,
                safe_get(row,"score_locaux"), safe_get(row,"score_visiteurs"),
                safe_get(row,"stade"), safe_get(row,"lieu"),
                safe_get(row,"arbitre"), safe_get(row,"journee"),
                to_seconds(safe_get(row,"possession_mt_1_total")),
                to_seconds(safe_get(row,"possession_mt_2_total")),
                to_seconds(safe_get(row,"temps_effectif_total")),
            ))
            id_match = cur.lastrowid

            # b) Possession + temps effectif
            for base, sql_stmt in (
                ("possession_mt_1", sql_pos1),
                ("possession_mt_2", sql_pos2),
                ("temps_effectif",  sql_temps),
            ):
                # Valeurs brutes de la feuille Excel
                sec_beziers = to_seconds(safe_get(row, f"{base}_beziers")) or 0
                sec_adv     = to_seconds(safe_get(row, f"{base}_equipe_adverse")) or 0

                if locaux_nom == "ASBH":            # cas où l’ASBH reçoit
                    sec_locaux, sec_visiteurs = sec_beziers, sec_adv
                    id_locaux_sql, id_visiteurs_sql = TEAM_ID["ASBH"], TEAM_ID[visiteurs_nom]
                else:                               # l’ASBH se déplace (exemple NICE-ASBH)
                    sec_locaux, sec_visiteurs = sec_adv, sec_beziers
                    id_locaux_sql, id_visiteurs_sql = id_locaux, TEAM_ID["ASBH"]

                # insertion locale puis visiteur
                cur.execute(sql_stmt, (sec_locaux,     id_locaux_sql,     id_match))
                cur.execute(sql_stmt, (sec_visiteurs,  id_visiteurs_sql,  id_match))



            # c) Scores (ASBH puis visiteurs)
            cur.execute(sql_score, (
                safe_get(row, f"essais_{locaux_nom.lower()}"),
                safe_get(row, f"transformations_{locaux_nom.lower()}"),
                safe_get(row, f"drops_{locaux_nom.lower()}"),
                safe_get(row, f"drop_tentes_{locaux_nom.lower()}"),
                safe_get(row, f"penalites_{locaux_nom.lower()}"),
                safe_get(row, f"penalites_{locaux_nom.lower()}"),
                id_locaux, id_match,
            ))

            cur.execute(sql_score, (
                safe_get(row, f"essais_{visiteurs_nom.lower()}"),
                safe_get(row, f"transformations_{visiteurs_nom.lower()}"),
                safe_get(row, f"drops_{visiteurs_nom.lower()}"),
                safe_get(row, f"drop_tentes_{visiteurs_nom.lower()}"),
                safe_get(row, f"penalites_{visiteurs_nom.lower()}"),
                safe_get(row, f"penalites_tentees_{visiteurs_nom.lower()}"),
                id_visiteurs, id_match,
            ))

            # d) Points, Localisation, Fin actions collectives
            for df_src, sql, id_col in (
                (pts_df, sql_points, "équipe"),
                (loc_df, sql_loc  , "equipe"),
                (fa_df , sql_fin  , "équipe"),
            ):
                for _, r in df_src.iterrows():
                    eq_raw   = str(r[id_col]).strip()
                    eq_norm  = normalize_name(eq_raw)
                    eq_canon = TEAM_NORM.get(eq_norm)

                    if not eq_canon:               # toujours pas trouvé → vrai message d'erreur
                        print(f"⚠️ Équipe inconnue dans {sql.split()[2]} : {eq_raw}")
                        continue

                    id_eq = TEAM_ID[eq_canon]      # ← cette fois on est sûr qu'il existe

                    if sql is sql_points:
                        cur.execute(sql, (
                            id_eq, id_match,
                            r["total"], r["positif"], r["neutre"], r["negatif"], r["action"]
                        ))
                    elif sql is sql_loc:
                        cur.execute(sql, (
                            id_eq, id_match, r["action"], r["portion_terrain"],
                            r["temps"], r["valeur"]
                        ))
                    else:  # sql_fin
                        cur.execute(sql, (
                            r["Total"], r["MT1"], r["MT2"], id_eq, id_match, r["action"]
                        ))

            # e) GPS
            for _, g in gps_df.iterrows():
                nom, prenom = g["Nom"].strip().upper(), g["Prénom"].strip()
                idj = joueurs_table.get((normalize_name(nom), normalize_name(prenom)))
                if not idj:
                    print(f"⚠️ GPS joueur absent : {nom} {prenom}")
                    continue
                cur.execute(sql_courir, (
                    idj, id_match,
                    g["Période"], g["Tps jeu (min)"], g["Dist. Tot. (m)"],
                    g["m/min"], g["% marche"], g["% intensité"],
                    g["Vmax (km/h)"], g["Nb accel"],
                ))


            # h) Stats individuelles longue forme
            inserted = 0
            for _, st in stats_df.iterrows():
                nom    = str(st["Nom"]).strip().upper()
                prenom = str(st["Prénom"]).strip()

                # Recherche exacte (nom + prénom)
                cur.execute(
                    "SELECT id_joueur FROM joueur WHERE UPPER(nom_joueur)=%s AND prenom_joueur=%s",
                    (nom, prenom),
                )
                res = cur.fetchone()

                # 1ère lettre du prénom (ex: "J.")
                if not res and len(prenom) <= 2 and "." in prenom:
                    initial = prenom.replace(".", "")
                    cur.execute(
                        "SELECT id_joueur FROM joueur WHERE UPPER(nom_joueur)=%s AND LEFT(prenom_joueur,1)=%s",
                        (nom, initial),
                    )
                    res = cur.fetchone()

                # Plusieurs homonymes possibles
                if not res:
                    cur.execute(
                        "SELECT id_joueur,prenom_joueur FROM joueur WHERE UPPER(nom_joueur)=%s",
                        (nom,),
                    )
                    rows = cur.fetchall()
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
                    MISSING_PLAYERS.add((nom, prenom))
                    continue

                cur.execute(sql_stat, (action, valeur, res[0], id_match))
                inserted += 1

            print(f"DEBUG: {inserted} stats importées pour le match {id_match}")

            # i) Calcul de l’IDP
            for _, pj in effectif_df.iterrows():
                # récupérer poste et temps de jeu
                poste = str(pj["poste"])
                minutes_jouees = to_seconds(pj["temps_de_jeu"]) or 0

                # retrouver l'id_joueur
                nom_norm    = normalize_name(pj["nom"])
                prenom_norm = normalize_name(pj["prenom"])
                id_joueur = joueurs_table.get((nom_norm, prenom_norm))
                if id_joueur is None:
                    print(f"⚠️ Joueur introuvable pour IDP (table preload) : {pj['nom']} {pj['prenom']}")
                    continue

                # récupérer toutes les actions individuelles de ce joueur sur ce match
                cur.execute(
                    "SELECT action, valeur FROM export_stat_match WHERE id_joueur=%s AND id_match=%s",
                    (id_joueur, id_match)
                )
                rows = cur.fetchall()

                # sommer par catégorie
                scores_cat = {
                    "attaque": 0.0, "defense": 0.0, "spec": 0.0,
                    "engagement": 0.0, "discipline": 0.0, "initiative": 0.0
                }
                for action, valeur in rows:
                    if action in COEFFICIENTS:
                        cat, coef = COEFFICIENTS[action]
                        scores_cat[cat] += coef * valeur

                # calcul du score brut
                poids_poste = PONDERATIONS[str(poste)]
                score_brut = sum(scores_cat[cat] * poids_poste[cat] for cat in scores_cat)

                # si vous voulez normaliser, définissez score_max (par exemple une constante ou un calcul)
                score_max = None
                idp_value = score_brut if score_max is None else score_brut / score_max

                # insérer dans idp (la colonne 'details' peut contenir le JSON des scores par catégorie)
                cur.execute("""
                    INSERT INTO idp (
                    id_joueur, id_match, poste, idp,
                    minutes_jouees, score_brut, score_max, details
                    ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s)
                """, (
                    id_joueur, id_match, poste, idp_value,
                    minutes_jouees, score_brut, score_max,
                    json.dumps(scores_cat, ensure_ascii=False)
                ))

            # ───────────────
            # 6) Validation de la transaction
            # ───────────────
            return id_match, list(MISSING_PLAYERS)
            conn.commit()
            print("✅ Import terminé avec succès.")

        except Exception as e:
            print("❌ Erreur pendant l'import:", e)
            traceback.print_exc()
            conn.rollback()
            raise

        finally:
            cur.close()
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
