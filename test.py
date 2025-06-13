# Liste de toutes les métriques (sans les "Total …")
metrics = [
    # Jeu au pied
    "Jeu au pied - Non renseigné",
    "Jeu au pied - Perte du ballon",
    "Jeu au pied - Sortie du ballon",
    "Jeu au pied - Injouable (perte)",
    "Jeu au pied - Positif",
    "Jeu au pied - Négatif",
    # Perte de balle
    "Perte de balle - Passe manquée",
    "Perte de balle - En avant",
    "Perte de balle - Perte du ballon",
    "Perte de balle - Négatif",
    # Faute règlement
    "Faute règlement - Jeu au sol",
    "Faute règlement - Hors jeu de ligne",
    "Faute règlement - Brutalité",
    "Faute règlement - Pénalité",
    "Faute règlement - CPP Concédé",
    "Faute règlement - Négatif",
    # Faute technique
    "Faute technique - Touche",
    "Faute technique - Jeu Courant",
    "Faute technique - En avant",
    "Faute technique - Mauvaise Passe",
    "Faute technique - Avantage",
    "Faute technique - Mêlée",
    "Faute technique - Conservation",
    "Faute technique - CPF Concédé",
    "Faute technique - Négatif",
    # Points
    "Points - Essai",
    "Points - Transformation",
    "Points - Sortie du ballon",
    "Points - Marque",
    "Points - Positif",
    "Points - Négatif",
    # Plaquage
    "Plaquage - Non renseigné",
    "Plaquage - Récupérateur",
    "Plaquage - Perte du ballon",
    "Plaquage - CPP Concédé",
    "Plaquage - Mêlée concédée",
    "Plaquage - Sortie du ballon",
    "Plaquage - Positif",
    "Plaquage - Négatif",
    # Plaquage manqué
    "Plaquage manqué - Non renseigné",
    "Plaquage manqué - Négatif",
    # Franchissement
    "Franchissement - Non renseigné",
    "Franchissement - Marque",
    "Franchissement - Positif",
    # Récupération
    "Récupération - Non renseigné",
    "Récupération - Positif",
    # Assistant
    "Assistant - Non renseigné",
    "Assistant - Positif",
    # Lanceur
    "Lanceur - Conservation",
    "Lanceur - Perte du ballon",
    "Lanceur - Mêlée concédée",
    "Lanceur - CPF Concédé",
    "Lanceur - Positif",
    "Lanceur - Négatif",
    # Sauteur
    "Sauteur - Conservation",
    "Sauteur - Perte du ballon",
    "Sauteur - Mêlée concédée",
    "Sauteur - CPF Concédé",
    "Sauteur - Positif",
    "Sauteur - Neutre",
    "Sauteur - Négatif",
    # Contreur
    "Contreur - Perte du ballon",
    "Contreur - Positif",
    # Pousseur
    "Pousseur - Conservation",
    "Pousseur - CPP Concédé",
    "Pousseur - CPP Obtenu",
    "Pousseur - CPF Obtenu",
    "Pousseur - Injouable",
    "Pousseur - Positif",
    "Pousseur - Neutre",
    "Pousseur - Négatif",
    # Soutien Off
    "Soutien Off - Non renseigné",
    "Soutien Off - Conservation",
    "Soutien Off - Perte du ballon",
    "Soutien Off - CPP Concédé",
    "Soutien Off - CPP Obtenu",
    "Soutien Off - Positif",
    "Soutien Off - Neutre",
    "Soutien Off - Négatif",
    # Porteur de balle
    "Porteur de balle - Non renseigné",
    "Porteur de balle - Conservation",
    "Porteur de balle - Perte du ballon",
    "Porteur de balle - CPP Concédé",
    "Porteur de balle - CPP Obtenu",
    "Porteur de balle - Positif",
    "Porteur de balle - Neutre",
    "Porteur de balle - Négatif",
    # Passeur
    "Passeur - Conservation",
    "Passeur - CPP Obtenu",
    "Passeur - Positif",
    # Contest Air
    "Contest Air - Gagne",
    "Contest Air - Perdu",
    "Contest Air - Conservation",
    "Contest Air - Perte du ballon",
    "Contest Air - Positif",
    "Contest Air - Négatif",
    # botteur
    "botteur - Conservation",
    "botteur - Perte du ballon",
    "botteur - Positif",
]

# Génération de COEFFICIENTS pour les postes 1 à 15
COEFFICIENTS = {
    str(poste): {metric: ("", None) for metric in metrics}
    for poste in range(1, 16)
}

# Exemple d'accès :
# COEFFICIENTS["1"]["Jeu au pied - Positif"]  -> ("", None)
