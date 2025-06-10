<?php
// login.php
session_start();
require_once 'db.php';
$pdo = getBD();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Si l’utilisateur est déjà authentifié, on le redirige vers la page d’accueil
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $err = 'Veuillez renseigner les deux champs.';
    } else {
        // Requête préparée pour récupérer le hash du mot de passe
        $sql = "SELECT id, password_hash, role FROM users WHERE username = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Connexion réussie : enregistrer en session
            session_regenerate_id(true); // important pour éviter le session fixation
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $username;
            $_SESSION['role']      = $user['role']; // facultatif, pour usage futur

            header('Location: index.php');
            exit;
        } else {
            $err = 'Identifiant ou mot de passe incorrect.';
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Connexion – ASBH</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: { primary: '#292E68', danger: '#A00E0F' },
          fontFamily: {
            sans: ['Inter','sans-serif'],
            title: ['"Bebas Neue"','cursive'],
            button: ['Manrope','sans-serif']
          }
        }
      }
    };
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Bebas+Neue&family=Manrope:wght@500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-[#A00E0F] via-[#5e0000] to-black text-white font-sans min-h-screen flex items-center justify-center">
  <div class="bg-black/70 border border-white/20 rounded-xl p-8 w-full max-w-md">
    <h1 class="text-2xl font-title mb-6 text-center">Connexion</h1>

    <?php if ($err): ?>
      <div class="bg-red-600 bg-opacity-50 text-red-100 px-4 py-2 rounded mb-4">
        <?= htmlspecialchars($err) ?>
      </div>
    <?php endif; ?>

    <form action="login.php" method="post" class="space-y-4">
      <div>
        <label for="username" class="block mb-1 font-semibold">Utilisateur</label>
        <input type="text" name="username" id="username" required
               class="w-full bg-white/20 text-white placeholder-gray-300 rounded px-3 py-2 focus:outline-none focus:ring"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
      <div>
        <label for="password" class="block mb-1 font-semibold">Mot de passe</label>
        <input type="password" name="password" id="password" required
               class="w-full bg-white/20 text-white placeholder-gray-300 rounded px-3 py-2 focus:outline-none focus:ring">
      </div>
      <button type="submit"
              class="w-full bg-primary hover:bg-[#1f2550] text-white font-bold px-4 py-2 rounded transition">
        Se connecter
      </button>
    </form>
  </div>
</body>
</html>
