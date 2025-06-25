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
    "1":{
        # Spécifique mêlée
        "Mêlées dominantes défensives": ("spec",  +6),
        "Mêlées dominantes offensives": ("spec",  +5),
        "Pénalités provoquées en mêlée": ("spec",  +7),
        "Pénalités concédées en mêlée": ("spec",  -8),
        "Stabilité (aucune mêlée effondrée)": ("spec",  +3),

        # Défense
        "Plaquages réussis": ("defense",  3),
        "Plaquages dominants (offensifs, gagnants)": ("defense",  6),
        "Plaquages d'arrêt (stop le porteur net)": ("defense",  5),
        "Plaquages subis (recul de +2m sur impact)": ("defense", -4),
        "Plaquages manqués": ("defense", -5),
        "Turnovers gagnés après un plaquage": ("defense",  8),
        "Actions négatives en défense": ("defense", -10),
        "Soutien défensif efficace": ("defense",  4),
        "Soutien défensif inefficace": ("defense", -2),
        "Soutien défensif inutile": ("defense", -3),

        # Attaque
        "Portées de balle": ("attaque",  2.5),
        "Mètres gagnés": ("attaque",  0.5),
        "Franchissements": ("attaque",  8),
        "1vs1 gagné": ("attaque",  5),
        "1vs1 neutre": ("attaque",  2),
        "1vs1 perdu": ("attaque", -4),
        "Défenseur battu": ("attaque",  6),
        "Soutien offensif efficace": ("attaque",  4),
        "Soutien offensif inefficace": ("attaque",  0),
        "Soutien offensif inutile": ("attaque", -3),
        "Passe réussie": ("attaque",  2),
        "Passe manquée": ("attaque", -3),
        "Offloads réussis": ("attaque",  6),
        "Essais marqués": ("attaque", 10),
        "Pertes de balle": ("attaque", -6),

        # Engagement
        "Participation aux rucks": ("engagement",  2),
        "Grattage réussi": ("engagement",  8),
        "Contest efficace": ("engagement",  4),
        "Ballon ralenti": ("engagement",  3),
        "Pénalité sur contest": ("engagement", -6),

        # Discipline
        "Fautes règlement": ("discipline",  -5),
        "Pénalités commises": ("discipline",  -8),
        "Cartons jaunes": ("discipline", -12),
        "Cartons rouges": ("discipline", -25),
        "Fautes dans notre camp (50m-22m)": ("discipline",  -8),

        # Initiative
        "Cad-déb réussi": ("initiative",  2.5),
        "Jouer un ballon rapidement": ("initiative",  3),
        "Prise d'initiative positive": ("initiative",  2),
        "Prise d'initiative neutre": ("initiative",  1.5),
        "Prise d'initiative négative": ("initiative", -2),
    },
    "3":{
        # Spécifique mêlée
        "Mêlées dominantes défensives": ("spec", +10),
        "Mêlées dominantes offensives": ("spec",  +7),
        "Pénalités provoquées en mêlée": ("spec", +10),
        "Pénalités concédées en mêlée": ("spec", -10),
        "Stabilité (aucune mêlée effondrée)": ("spec",  +3),

        # Défense
        "Plaquages réussis": ("defense",  2.5),
        "Plaquages dominants (offensifs, gagnants)": ("defense",  7),
        "Plaquages d'arrêt (stop le porteur net)": ("defense",  6),
        "Plaquages subis (recul de +2m sur impact)": ("defense", -3),
        "Plaquages manqués": ("defense", -4),
        "Turnovers gagnés après un plaquage": ("defense",  7),
        "Actions négatives en défense": ("defense", -8),
        "Soutien défensif efficace": ("defense",  3),
        "Soutien défensif inefficace": ("defense", -2),
        "Soutien défensif inutile": ("defense", -3),

        # Attaque
        "Portées de balle": ("attaque",  2),
        "Mètres gagnés": ("attaque",  0.4),
        "Franchissements": ("attaque",  6),
        "1vs1 gagné": ("attaque",  4),
        "1vs1 neutre": ("attaque",  2),
        "1vs1 perdu": ("attaque", -3),
        "Défenseur battu": ("attaque",  5),
        "Soutien offensif efficace": ("attaque",  3),
        "Soutien offensif inefficace": ("attaque",  0),
        "Soutien offensif inutile": ("attaque", -3),
        "Passe réussie": ("attaque",  1.5),
        "Passe manquée": ("attaque", -3),
        "Offloads réussis": ("attaque",  5),
        "Essais marqués": ("attaque", 10),
        "Pertes de balle": ("attaque", -5),

        # Engagement
        "Participation aux rucks": ("engagement",  1.5),
        "Grattage réussi": ("engagement",  7),
        "Contest efficace": ("engagement",  3),
        "Ballon ralenti": ("engagement",  3),
        "Pénalité sur contest": ("engagement", -5),

        # Discipline
        "Fautes règlement": ("discipline",  -4),
        "Pénalités commises": ("discipline",  -7),
        "Cartons jaunes": ("discipline", -10),
        "Cartons rouges": ("discipline", -20),
        "Fautes dans notre camp (50m-22m)": ("discipline",  -7),

        # Initiative
        "Cad-déb réussi": ("initiative",  2.5),
        "Jouer un ballon rapidement": ("initiative",  3),
        "Prise d'initiative positive": ("initiative",  2),
        "Prise d'initiative neutre": ("initiative",  1.5),
        "Prise d'initiative négative": ("initiative", -2),
        },
    "2":{
        # Spécifique mêlée / lancer
        "Mêlées dominantes défensives": ("spec", 4),
        "Mêlées dominantes offensives": ("spec", 3),
        "Mêlée volée": ("spec", 6),
        "1ère ligne écroulée": ("spec", -3),
        "1ère ligne relevée": ("spec", -3),
        "Pénalité provoquée en mêlée": ("spec", 5),
        "Stabilité (aucune mêlée effondrée)": ("spec", 3),
        "Lancer réussi": ("spec", 5),
        "Lancer pas droit": ("spec", -6),
        "Lancer mal réalisé": ("spec", -4),

        # Attaque
        "Portées de balle": ("attaque", 3),
        "Mètres franchis": ("attaque", 0.7),
        "Franchissements": ("attaque", 6),
        "1vs1 gagné": ("attaque", 4),
        "1vs1 neutre": ("attaque", 2),
        "1vs1 perdu": ("attaque", -3),
        "Défenseur battu": ("attaque", 5),
        "Soutien offensif efficace": ("attaque", 4),
        "Soutien offensif inefficace": ("attaque", -2),
        "Soutien offensif inutile": ("attaque", -4),
        "Passes réussies": ("attaque", 2),
        "Passes manquées": ("attaque", -4),
        "Offloads réussis": ("attaque", 6),
        "Essais marqués": ("attaque", 10),
        "Pertes de balle": ("attaque", -7),

        # Défense
        "Plaquages réussis": ("defense", 3),
        "Plaquages dominants": ("defense", 5),
        "Plaquages d'arrêt": ("defense", 4),
        "Plaquages subis ou ratés": ("defense", -5),
        "Récupération grâce au plaquage": ("defense", 6),
        "Soutien défensif efficace": ("defense", 4),
        "Soutien défensif inefficace": ("defense", -3),
        "Soutien défensif inutile": ("defense", -5),

        # Engagement physique
        "Participation aux rucks": ("engagement", 3),
        "Grattages réussis": ("engagement", 7),
        "Contest efficace": ("engagement", 6),
        "Ballon ralenti": ("engagement", 5),

        # Discipline
        "Fautes règlementaires": ("discipline", -6),
        "Fautes techniques": ("discipline", -4),
        "Cartons jaunes": ("discipline", -12),
        "Cartons rouges": ("discipline", -25),

        # Prise d’initiative
        "Cad-déb réussi": ("initiative", 3),
        "Jouer un ballon rapidement": ("initiative", 3.5),
        "Prise d'initiative positive": ("initiative", 3),
        "Prise d'initiative neutre": ("initiative", 2),
        "Prise d'initiative négative": ("initiative", -3),
        },
    "4":{
        # Mêlée & Touche
        "Mêlées dominantes défensives": ("spec", 4),
        "Mêlées dominantes offensives": ("spec", 3),
        "Mêlée volée": ("spec", 5),
        "Stabilité (aucune mêlée effondrée)": ("spec", 3),
        "Sauts en touche positifs": ("spec", 4),
        "Sauts en touche neutres": ("spec", 2),
        "Sauts en touche négatifs": ("spec", -4),
        "Contre en touche positif": ("spec", 5),
        "Contre en touche neutre": ("spec", 2),
        "Contre en touche négatif": ("spec", -4),

        # Défense
        "Plaquages réussis": ("defense", 3),
        "Plaquages dominants": ("defense", 4),
        "Plaquages d'arrêt": ("defense", 3),
        "Plaquages subis ou ratés": ("defense", -4),
        "Récupération grâce au plaquage": ("defense", 6),
        "Soutien défensif efficace": ("defense", 4),
        "Soutien défensif inefficace": ("defense", -3),
        "Soutien défensif inutile": ("defense", -5),

        # Attaque
        "Portées de balle": ("attaque", 3),
        "Mètres franchis": ("attaque", 0.5),
        "Franchissements": ("attaque", 6),
        "1vs1 gagné": ("attaque", 4),
        "1vs1 neutre": ("attaque", 2),
        "1vs1 perdu": ("attaque", -3),
        "Défenseur battu": ("attaque", 5),
        "Soutien offensif efficace": ("attaque", 4),
        "Soutien offensif inefficace": ("attaque", -2),
        "Soutien offensif inutile": ("attaque", -4),
        "Passes réussies": ("attaque", 2),
        "Passes manquées": ("attaque", -4),
        "Offloads réussis": ("attaque", 6),
        "Essais marqués": ("attaque", 10),
        "Pertes de balle": ("attaque", -7),

        # Engagement physique
        "Participation aux rucks": ("engagement", 2.5),
        "Grattages réussis": ("engagement", 6),
        "Contest efficace": ("engagement", 5),
        "Ballon ralenti": ("engagement", 4),

        # Discipline
        "Fautes règlementaires": ("discipline", -5),
        "Fautes techniques": ("discipline", -4),
        "Cartons jaunes": ("discipline", -12),
        "Cartons rouges": ("discipline", -25),

        # Prise d’initiative
        "Cad-déb réussi": ("initiative", 3),
        "Jouer un ballon rapidement": ("initiative", 3.5),
        "Prise d'initiative positive": ("initiative", 3),
        "Prise d'initiative neutre": ("initiative", 2),
        "Prise d'initiative négative": ("initiative", -3),
        },
    "5":{
        # Mêlée & Touche
        "Mêlées dominantes défensives": ("spec", 5),
        "Mêlées dominantes offensives": ("spec", 4),
        "Mêlée volée": ("spec", 6),
        "Stabilité (aucune mêlée effondrée)": ("spec", 4),
        "Sauts en touche positifs": ("spec", 3),
        "Sauts en touche neutres": ("spec", 2),
        "Sauts en touche négatifs": ("spec", -3),
        "Contre en touche positif": ("spec", 6),
        "Contre en touche neutre": ("spec", 2),
        "Contre en touche négatif": ("spec", -3),

        # Défense
        "Plaquages réussis": ("defense", 2.5),
        "Plaquages dominants": ("defense", 5),
        "Plaquages d'arrêt": ("defense", 4),
        "Plaquages subis ou ratés": ("defense", -3),
        "Récupération grâce au plaquage": ("defense", 7),
        "Soutien défensif efficace": ("defense", 3),
        "Soutien défensif inefficace": ("defense", -4),
        "Soutien défensif inutile": ("defense", -6),

        # Attaque
        "Portées de balle": ("attaque", 4),
        "Mètres franchis": ("attaque", 0.3),
        "Franchissements": ("attaque", 5),
        "1vs1 gagné": ("attaque", 3),
        "1vs1 neutre": ("attaque", 3),
        "1vs1 perdu": ("attaque", -4),
        "Défenseur battu": ("attaque", 4),
        "Soutien offensif efficace": ("attaque", 3),
        "Soutien offensif inefficace": ("attaque", -3),
        "Soutien offensif inutile": ("attaque", -5),
        "Passes réussies": ("attaque", 1.5),
        "Passes manquées": ("attaque", -5),
        "Offloads réussis": ("attaque", 4),
        "Essais marqués": ("attaque", 12),
        "Pertes de balle": ("attaque", -8),

        # Engagement physique
        "Participation aux rucks": ("engagement", 3),
        "Grattages réussis": ("engagement", 8),
        "Contest efficace": ("engagement", 6),
        "Ballon ralenti": ("engagement", 5),

        # Discipline
        "Fautes règlementaires": ("discipline", -6),
        "Fautes techniques": ("discipline", -5),
        "Cartons jaunes": ("discipline", -15),
        "Cartons rouges": ("discipline", -30),

        # Prise d’initiative
        "Cad-déb réussi": ("initiative", 6),
        "Jouer un ballon rapidement": ("initiative", 7),
        "Prise d'initiative positive": ("initiative", 6),
        "Prise d'initiative neutre": ("initiative", 4),
        "Prise d'initiative négative": ("initiative", -6),
    },
    "6":{
        # Touche
        "Saut en touche positif": ("spec", 6),
        "Saut en touche neutre": ("spec", 3),
        "Saut en touche négatif": ("spec", 1),
        "Contre en touche positif": ("spec", 7),
        "Contre en touche neutre": ("spec", 5),
        "Contre en touche négatif": ("spec", 3),
        "Participation aux rucks": ("spec", 5),
        "Grattages réussis": ("spec", 6),
        "Contest efficace": ("spec", 5),
        "Ballon ralenti": ("spec", 4),

        # Défense
        "Plaquages réussis": ("defense", 4),
        "Plaquages dominants": ("defense", 5),
        "Plaquages d'arrêt": ("defense", 4),
        "Plaquages subis ou ratés": ("defense", 2),
        "Récupération grâce au plaquage": ("defense", 6),
        "Soutiens défensifs efficaces": ("defense", 5),
        "Soutiens défensifs inefficaces": ("defense", 3),
        "Soutiens défensifs inutiles": ("defense", 1),

        # Attaque
        "Portées de balle": ("attaque", 3),
        "Mètres franchis": ("attaque", 1),
        "Franchissement": ("attaque", 5),
        "1vs1 gagné": ("attaque", 6),
        "1vs1 neutre": ("attaque", 3),
        "1vs1 perdu": ("attaque", 1),
        "Défenseur battu": ("attaque", 5),
        "Soutiens offensifs efficaces": ("attaque", 5),
        "Soutiens offensifs inefficaces": ("attaque", 3),
        "Soutiens offensifs inutiles": ("attaque", 1),
        "Passe réussie": ("attaque", 3),
        "Passe manquée": ("attaque", 1),
        "Offloads réussis": ("attaque", 5),
        "Essais": ("attaque", 10),
        "Perte de balle": ("attaque", 2),

        # engagement 
        "Participation aux rucks": ("engagement", 3),
        "Grattages réussis": ("engagement", 4),
        "Contre-ruck réussis": ("engagement", 5),

        # Discipline (- = pénalité)
        "Fautes règlementaires": ("discipline", -2),
        "Fautes techniques": ("discipline", -3),
        "Cartons jaunes": ("discipline", -5),
        "Cartons rouges": ("discipline", -10),

        # Prise d’initiative
        "Cad-déb réussi": ("initiative", 3),
        "Jouer un ballon rapidement": ("initiative", 3.5),
        "Prise d'initiative positive": ("initiative", 3),
        "Prise d'initiative neutre": ("initiative", 2),
        "Prise d'initiative négative": ("initiative", -3),
    },
    "7":{
        # Touche
        "Saut en touche positif": ("spec", 7),
        "Saut en touche neutre": ("spec", 4),
        "Saut en touche négatif": ("spec", 2),
        "Contre en touche positif": ("spec", 8),
        "Contre en touche neutre": ("spec", 6),
        "Contre en touche négatif": ("spec", 4),
        "Participation aux rucks": ("spec", 6),
        "Grattages réussis": ("spec", 8),
        "Contest efficace": ("spec", 7),
        "Ballon ralenti": ("spec", 6),

        # Défense
        "Plaquages réussis": ("defense", 5),
        "Plaquages dominants": ("defense", 6),
        "Plaquages d'arrêt": ("defense", 5),
        "Plaquages subis ou ratés": ("defense", 3),
        "Récupération grâce au plaquage": ("defense", 8),
        "Soutiens défensifs efficaces": ("defense", 6),
        "Soutiens défensifs inefficaces": ("defense", 4),
        "Soutiens défensifs inutiles": ("defense", 2),

        # Attaque
        "Portées de balle": ("attaque", 2.5),
        "Mètres franchis": ("attaque", 1),
        "Franchissement": ("attaque", 4),
        "1vs1 gagné": ("attaque", 5),
        "1vs1 neutre": ("attaque", 3),
        "1vs1 perdu": ("attaque", 2),
        "Défenseur battu": ("attaque", 5),
        "Soutiens offensifs efficaces": ("attaque", 5),
        "Soutiens offensifs inefficaces": ("attaque", 3),
        "Soutiens offensifs inutiles": ("attaque", 2),
        "Passe réussie": ("attaque", 4),
        "Passe manquée": ("attaque", 2),
        "Offloads réussis": ("attaque", 6),
        "Essais": ("attaque", 8),
        "Perte de balle": ("attaque", 3),

        # engagement
        "Participation aux rucks": ("engagement", 3),
        "Grattages réussis": ("engagement", 5),
        "Contre-ruck réussis": ("engagement", 6),

        # Discipline
        "Fautes règlementaires": ("discipline", -3),
        "Fautes techniques": ("discipline", -4),
        "Cartons jaunes": ("discipline", -6),
        "Cartons rouges": ("discipline", -12),

        # Prise d’initiative
        "Cad-déb réussi": ("initiative", 3),
        "Jouer un ballon rapidement": ("initiative", 3.5),
        "Prise d'initiative positive": ("initiative", 3),
        "Prise d'initiative neutre": ("initiative", 2),
        "Prise d'initiative négative": ("initiative", -3),
    },
    "8":{
        # Touche
        "Saut en touche positif": ("spec", 5),
        "Saut en touche neutre": ("spec", 3),
        "Saut en touche négatif": ("spec", 1),
        "Contre en touche positif": ("spec", 6),
        "Contre en touche neutre": ("spec", 4),
        "Contre en touche négatif": ("spec", 2),
        "Participation aux rucks": ("spec", 5),
        "Grattages réussis": ("spec", 5),
        "Contest efficace": ("spec", 5),
        "Ballon ralenti": ("spec", 4),

        # Défense
        "Plaquages réussis": ("defense", 3),
        "Plaquages dominants": ("defense", 5),
        "Plaquages d'arrêt": ("defense", 4),
        "Plaquages subis ou ratés": ("defense", 2),
        "Récupération grâce au plaquage": ("defense", 5),
        "Soutiens défensifs efficaces": ("defense", 4),
        "Soutiens défensifs inefficaces": ("defense", 3),
        "Soutiens défensifs inutiles": ("defense", 1),

        # Attaque
        "Portées de balle": ("attaque", 5),
        "Mètres franchis": ("attaque", 2),
        "Franchissement": ("attaque", 7),
        "1vs1 gagné": ("attaque", 7),
        "1vs1 neutre": ("attaque", 4),
        "1vs1 perdu": ("attaque", 2),
        "Défenseur battu": ("attaque", 6),
        "Soutiens offensifs efficaces": ("attaque", 6),
        "Soutiens offensifs inefficaces": ("attaque", 4),
        "Soutiens offensifs inutiles": ("attaque", 2),
        "Passe réussie": ("attaque", 5),
        "Passe manquée": ("attaque", 2),
        "Offloads réussis": ("attaque", 7),
        "Essais": ("attaque", 12),
        "Perte de balle": ("attaque", 3),

        # Ruck / engagement
        "Participation aux rucks": ("engagement", 3),
        "Grattages réussis": ("engagement", 4),
        "Contre-ruck réussis": ("engagement", 5),

        # Discipline
        "Fautes règlementaires": ("discipline", -2),
        "Fautes techniques": ("discipline", -3),
        "Cartons jaunes": ("discipline", -4),
        "Cartons rouges": ("discipline", -8),

        # Prise d’initiative
        "Cad-déb réussi": ("initiative", 6),
        "Jouer un ballon rapidement": ("initiative", 7),
        "Prise d'initiative positive": ("initiative", 6),
        "Prise d'initiative neutre": ("initiative", 4),
        "Prise d'initiative négative": ("initiative", -6),
},
    "9":{
        # Précision & gestion (30 %)
        "Jeu au pied positif / efficace": ("spec",  6),
        "Jeu au pied négatif / pas efficace": ("spec", -6),
        "Passes réussies": ("spec",  2),
        "Passes manquées": ("spec", -3),
        "Passes décisives": ("spec",  5),

        # Attaque (20 %)
        "Portées de balle": ("attaque",  3),
        "Mètres parcourus": ("attaque",  0.5),
        "Franchissements": ("attaque",  6),
        "Prises d'intervalle réussies": ("attaque",  6),
        "1v1 gagné": ("attaque",  5),
        "1v1 neutre": ("attaque",  3),
        "1v1 perdu": ("attaque", -3),
        "Offloads réussis": ("attaque",  4),
        "Essais marqués": ("attaque", 10),
        "Passe réussie": ("attaque",  2),      # singulier (table Attaque)
        "Passe manquée": ("attaque", -3),      # singulier (table Attaque)
        "Perte de balle": ("attaque", -6),

        # Défense (20 %)
        "Plaquages réussis": ("defense",  3),
        "Plaquages dominants": ("defense",  5),
        "Plaquages d'arrêt": ("defense",  4),
        "Plaquages subis ou ratés": ("defense", -5),
        "Récupération (bon placement défensif en R2)": ("defense",  6),
        "Mauvais placement défensif": ("defense", -6),
        "Soutien défensif positif": ("defense",  4),
        "Soutien défensif négatif": ("defense", -4),

        # Engagement physique (10 %)
        "Participation aux rucks offensifs": ("engagement",  3),
        "Participation aux rucks défensifs": ("engagement",  4),
        "Grattages réussis": ("engagement",  6),
        "Contest efficace": ("engagement",  5),
        "Ballon ralenti": ("engagement",  3),

        # Discipline / zones critiques (10 %)
        "Faute réglementaire": ("discipline", -5),
        "Faute technique": ("discipline",   -5),
        "Carton jaune": ("discipline",    -12),
        "Carton rouge": ("discipline",    -25),
        "Fautes dans notre camp": ("discipline",  -8),

        # Prise d’initiative (10 %)
        "Cad-déb réussi": ("initiative",  6),
        "Jouer un ballon rapidement": ("initiative",  7),
        "Prise d'initiative positive": ("initiative",  6),
        "Prise d'initiative neutre": ("initiative",  4),
        "Prise d'initiative négative": ("initiative", -6),
    },
    "10":{
        # Précision & gestion (30 %)
        "Jeu au pied positif / efficace": ("spec",  5),
        "Jeu au pied négatif / inefficace": ("spec", -5),
        "Pénaltouche trouvée": ("spec",  6),
        "Pénaltouche non trouvée": ("spec", -6),
        "Passes réussies": ("spec",  2),
        "Passes manquées": ("spec", -3),
        "Passes décisives": ("spec",  5),

        # Attaque (25 %)
        "Porteur de balle": ("attaque",  3),
        "Mètres parcourus": ("attaque",  0.5),
        "Franchissement": ("attaque",  6),
        "1 vs 1 gagné": ("attaque",  4),
        "1 vs 1 neutre": ("attaque",  2),
        "1 vs 1 perdu": ("attaque", -3),
        "Perte de balle": ("attaque", -6),
        "Offloads réussis": ("attaque",  6),
        "Essais": ("attaque", 10),
        "Passes réussies": ("attaque",  1),
        "Passes manquées": ("attaque", -4),
        "Passes décisives": ("attaque",  8),

        # Défense (20 %)
        "Plaquages réussis": ("defense",  3),
        "Plaquages dominants": ("defense",  5),
        "Plaquages d'arrêt": ("defense",  4),
        "Plaquages subis ou ratés": ("defense", -5),
        "Récupération (bon placement en R3)": ("defense",  6),
        "Mauvais placement défensif": ("defense", -6),
        "Soutien défensif positif": ("defense",  4),
        "Soutien défensif négatif": ("defense", -4),

        # Engagement physique (10 %)
        "Participation aux rucks": ("engagement",  2.5),
        "Grattages réussis": ("engagement",  6),
        "Contest efficace": ("engagement",  4),
        "Ballon ralenti": ("engagement",  3),

        # Discipline (15 %)
        "Fautes réglementaires": ("discipline", -5),
        "Fautes techniques": ("discipline",   -4),
        "Cartons jaunes": ("discipline",  -12),
        "Cartons rouges": ("discipline",  -25),
        "Fautes dans notre camp": ("discipline",  -8),

        # Prise d’initiative (10 %)
        "Cad-déb réussi": ("initiative",  6),
        "Jouer un ballon rapidement": ("initiative",  7),
        "Prise d'initiative positive": ("initiative",  6),
        "Prise d'initiative neutre": ("initiative",  4),
        "Prise d'initiative négative": ("initiative", -6),
    },
    "11":{
        # Jeu au pied & jeu aérien
        "Jeu au pied efficace": ("spec",  5),
        "Jeu au pied pas efficace": ("spec", -5),
        "Contest Air positif": ("spec",  6),
        "Contest Air neutre": ("spec",  3),
        "Contest Air négatif": ("spec", -4),

        # Attaque
        "Portées de balle": ("attaque",  3),
        "Mètres parcourus": ("attaque",  0.3),
        "Franchissements": ("attaque",  4),
        "1vs1 gagné": ("attaque",  5),
        "1vs1 neutre": ("attaque",  2),
        "1vs1 perdu": ("attaque", -4),
        "Défenseur battu": ("attaque",  5),
        "Offloads réussis": ("attaque",  4),
        "Passe réussie": ("attaque",  2),
        "Passe manquée": ("attaque", -3),
        "Passe décisive": ("attaque",  6),
        "Essais marqués": ("attaque", 10),

        # Défense
        "Plaquages réussis": ("defense",  3),
        "Plaquages dominants": ("defense",  5),
        "Plaquages d'arrêt": ("defense",  4),
        "Plaquages subis": ("defense", -3),
        "Plaquages ratés": ("defense", -4),
        "Récupération (bon placement défensif)": ("defense",  6),
        "Soutien défensif positif": ("defense",  4),
        "Soutien défensif négatif": ("defense", -5),
        "Mauvais placement défensif": ("defense", -6),

        # Engagement physique
        "Participation aux rucks": ("engagement",  3),
        "Grattages réussis": ("engagement",  4),
        "Contest efficace": ("engagement",  5),
        "Ballon ralenti": ("engagement",  3),

        # Discipline
        "Fautes règlementaires": ("discipline", -5),
        "Fautes techniques": ("discipline", -6),
        "Cartons jaunes": ("discipline", -12),
        "Cartons rouges": ("discipline", -25),
        "Fautes dans notre camp": ("discipline", -8),

        # Prise d’initiative
        "Cad-déb": ("initiative",  5),
        "Jouer un ballon rapidement": ("initiative",  6),
        "Prise d'initiative positive": ("initiative",  6),
        "Prise d'initiative neutre": ("initiative",  3),
        "Prise d'initiative négative": ("initiative", -4),
    },
    "12":{
        # Jeu au pied
        "Jeu au pied efficace": ("spec",  4),
        "Jeu au pied neutre": ("spec",  2),
        "Jeu au pied inefficace": ("spec", -3),
        "Franchissement": ("spec",  3),


        # Attaque
        "Portée de balle": ("attaque",  4),
        "Mètre parcouru": ("attaque",  0.5),
        "Franchissement": ("attaque",  5),
        "1vs1 gagné": ("attaque",  4),
        "1vs1 neutre": ("attaque",  2),
        "1vs1 perdu": ("attaque", -2),
        "Défenseur battu": ("attaque",  3),
        "Offload réussi": ("attaque",  4),
        "Passe réussie": ("attaque",  2),
        "Passe manquée": ("attaque", -3),
        "Passe décisive": ("attaque",  6),
        "Essai marqué": ("attaque",  8),

        # Défense
        "Plaquage réussi": ("defense",  3),
        "Plaquage dominant": ("defense",  4),
        "Plaquage d'arrêt": ("defense",  3),
        "Plaquage subi": ("defense", -3),
        "Plaquage raté": ("defense", -5),
        "Récupération de ballon": ("defense",  6),
        "Soutien défensif positif": ("defense",  3),
        "Soutien défensif négatif": ("defense", -3),

        # Engagement physique
        "Participation au ruck": ("engagement",  3),
        "Grattage réussi": ("engagement",  6),
        "Contest efficace": ("engagement",  4),
        "Ballon ralenti": ("engagement",  3),

        # Discipline / zones critiques
        "Faute réglementaire": ("discipline", -6),
        "Faute technique": ("discipline", -6),
        "Carton jaune": ("discipline", -12),
        "Carton rouge": ("discipline", -25),
        "Faute dans notre camp": ("discipline", -10),

        # Prise d’initiative
        "Cad-déb réussi": ("initiative",  5),
        "Jouer un ballon rapidement": ("initiative",  6),
        "Prise d'initiative positive": ("initiative",  6),
        "Prise d'initiative neutre": ("initiative",  2),
        "Prise d'initiative négative": ("initiative", -5),
    },
    "13":{
        # Jeu au pied
        "Jeu au pied efficace": ("spec",  4),
        "Jeu au pied neutre": ("spec",  2),
        "Jeu au pied inefficace": ("spec", -3),
        "Franchissement": ("spec",  6),


        # Attaque
        "Portée de balle": ("attaque",  4),
        "Mètre parcouru": ("attaque",  0.4),
        "Franchissement": ("attaque",  6),
        "1vs1 gagné": ("attaque",  5),
        "1vs1 neutre": ("attaque",  2),
        "1vs1 perdu": ("attaque", -2),
        "Défenseur battu": ("attaque",  5),
        "Offload réussi": ("attaque",  3),
        "Passe réussie": ("attaque",  2),
        "Passe manquée": ("attaque", -3),
        "Passe décisive": ("attaque",  6),
        "Essai marqué": ("attaque",  8),

        # Défense
        "Plaquage réussi": ("defense",  3),
        "Plaquage dominant": ("defense",  4),
        "Plaquage d'arrêt": ("defense",  3),
        "Plaquage subi": ("defense", -3),
        "Plaquage raté": ("defense", -5),
        "Récupération de ballon": ("defense",  6),
        "Soutien défensif positif": ("defense",  3),
        "Soutien défensif négatif": ("defense", -3),

        # Engagement physique
        "Participation au ruck": ("engagement",  3),
        "Grattage réussi": ("engagement",  5),
        "Contest efficace": ("engagement",  3),
        "Ballon ralenti": ("engagement",  3),

        # Discipline / zones critiques
        "Faute réglementaire": ("discipline", -6),
        "Faute technique": ("discipline", -6),
        "Carton jaune": ("discipline", -12),
        "Carton rouge": ("discipline", -25),
        "Faute dans notre camp": ("discipline", -10),

        # Prise d’initiative
        "Cad-déb réussi": ("initiative",  5),
        "Jouer un ballon rapidement": ("initiative",  6),
        "Prise d'initiative positive": ("initiative",  6),
        "Prise d'initiative neutre": ("initiative",  2),
        "Prise d'initiative négative": ("initiative", -5),
    },
    "14":{
        # Jeu au pied & jeu aérien
        "Jeu au pied efficace": ("spec",  5),
        "Jeu au pied pas efficace": ("spec", -5),
        "Contest Air positif": ("spec",  6),
        "Contest Air neutre": ("spec",  3),
        "Contest Air négatif": ("spec", -4),

        # Attaque
        "Portées de balle": ("attaque",  3),
        "Mètres parcourus": ("attaque",  0.3),
        "Franchissements": ("attaque",  4),
        "1vs1 gagné": ("attaque",  5),
        "1vs1 neutre": ("attaque",  2),
        "1vs1 perdu": ("attaque", -4),
        "Défenseur battu": ("attaque",  5),
        "Offloads réussis": ("attaque",  4),
        "Passe réussie": ("attaque",  2),
        "Passe manquée": ("attaque", -3),
        "Passe décisive": ("attaque",  6),
        "Essais marqués": ("attaque", 10),

        # Défense
        "Plaquages réussis": ("defense",  3),
        "Plaquages dominants": ("defense",  5),
        "Plaquages d'arrêt": ("defense",  4),
        "Plaquages subis": ("defense", -3),
        "Plaquages ratés": ("defense", -4),
        "Récupération (bon placement défensif)": ("defense",  6),
        "Soutien défensif positif": ("defense",  4),
        "Soutien défensif négatif": ("defense", -5),
        "Mauvais placement défensif": ("defense", -6),

        # Engagement physique
        "Participation aux rucks": ("engagement",  3),
        "Grattages réussis": ("engagement",  4),
        "Contest efficace": ("engagement",  5),
        "Ballon ralenti": ("engagement",  3),

        # Discipline
        "Fautes règlementaires": ("discipline", -5),
        "Fautes techniques": ("discipline", -6),
        "Cartons jaunes": ("discipline", -12),
        "Cartons rouges": ("discipline", -25),
        "Fautes dans notre camp": ("discipline", -8),

        # Prise d’initiative
        "Cad-déb": ("initiative",  5),
        "Jouer un ballon rapidement": ("initiative",  6),
        "Prise d'initiative positive": ("initiative",  6),
        "Prise d'initiative neutre": ("initiative",  3),
        "Prise d'initiative négative": ("initiative", -4),
    },
    "15":{
        # Jeu au pied & relance
        "Jeu au pied positif": ("spec",  6),
        "Jeu au pied pas efficace": ("spec", -6),
        "Relance positive": ("spec",  7),
        "Relance négative": ("spec", -5),
        "Contest air positif": ("spec",  4),
        "Contest air négatif": ("spec", -5),
        "Récupération (bon placement en R3)": ("spec",  6),

        # Attaque
        "Portées de balle": ("attaque",  4),
        "Mètres parcourus": ("attaque",  0.4),
        "Franchissements": ("attaque",  5),
        "1vs1 gagné": ("attaque",  5),
        "1vs1 neutre": ("attaque",  3),
        "1vs1 perdu": ("attaque", -3),
        "Défenseur battu": ("attaque",  5),
        "Offloads réussis": ("attaque",  3),
        "Passe réussie": ("attaque",  2),
        "Passe manquée": ("attaque", -2),
        "Passe décisive": ("attaque",  6),
        "Essais marqués": ("attaque", 10),

        # Défense
        "Plaquages réussis": ("defense",  4),
        "Plaquages dominants": ("defense",  5),
        "Plaquages d'arrêt": ("defense",  4),
        "Plaquages subis": ("defense", -3),
        "Plaquages ratés": ("defense", -3),
        "Récupération": ("defense",  6),
        "Soutien défensif positif": ("defense",  5),
        "Soutien défensif négatif": ("defense", -5),

        # Engagement physique
        "Participation aux rucks": ("engagement",  3),
        "Grattages réussis": ("engagement",  4),
        "Contest efficace": ("engagement",  4),
        "Ballon ralenti": ("engagement",  3),

        # Discipline / zones critiques
        "Fautes règlementaires": ("discipline", -6),
        "Fautes techniques": ("discipline", -5),
        "Cartons jaunes": ("discipline", -12),
        "Cartons rouges": ("discipline", -25),
        "Fautes dans notre camp": ("discipline", -8),

        # Prise d’initiative
        "Cad-déb": ("initiative",  6),
        "Jouer un ballon rapidement": ("initiative",  7),
        "Prise d'initiative positive": ("initiative",  8),
        "Prise d'initiative neutre": ("initiative",  5),
        "Prise d'initiative négative": ("initiative", -5),
    }
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

            def norm_action(s: str) -> str:
                return (
                    s.strip()
                    .lower()
                    .replace("é", "e")
                    .replace("è", "e")
                    .replace("à", "a")
    )

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
                coeffs_poste = COEFFICIENTS.get(str(poste), {})
                for action, valeur in rows:
                    if action in coeffs_poste:
                        cat, coef = coeffs_poste[action]
                        scores_cat[cat] += coef * valeur


                # calcul du score brut
                poids_poste = PONDERATIONS[str(poste)]
                score_brut = sum(scores_cat[c] * poids_poste[c] for c in scores_cat)
                facteur_temps = minutes_jouees / 80 
                score_brut *= facteur_temps

                # si vous voulez normaliser, définissez score_max (par exemple une constante ou un calcul)
                score_max = 100
                idp_value = max(0, min(100, 100 * score_brut / score_max))

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
            
            conn.commit()
            print("✅ Import terminé avec succès.")
            return id_match, list(MISSING_PLAYERS)

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
