<?php
session_start();
$_SESSION = [];
session_unset();
session_destroy();

// (Optionnel) supprimer le cookie de session
if (ini_get("session.use_cookies")) {
    setcookie(session_name(), '', time() - 3600, '/');
}

header('Location: login.php');
exit;
