<?php
// auth.php
// session_start();

// Si l’utilisateur n’est pas connecté, on redirige vers login.php
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// (Optionnel) Si vous souhaitez vérifier un rôle “entraineur” seulement :
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'entraineur') {
    // On peut afficher un message d’erreur ou rediriger ailleurs
    echo 'Accès refusé : vous devez être entraîneur.';
    exit;
}
?>
