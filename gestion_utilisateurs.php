<?php
// gestion_utilisateurs.php
require_once 'auth.php';
require_once 'db.php';
$pdo = getBD();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$current = basename($_SERVER['PHP_SELF']);

$errors = [];
$success = '';

// Création d’un nouvel utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errors[] = 'Les deux champs sont obligatoires.';
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Ce nom d’utilisateur existe déjà.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'entraineur')");
            $stmt->execute([$username, $hash]);
            $success = "Utilisateur « $username » créé avec succès.";
        }
    }
}

// Suppression d’un utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $delId = intval($_POST['user_id']);
    if ($delId === $_SESSION['user_id']) {
        $errors[] = 'Vous ne pouvez pas supprimer votre propre compte.';
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$delId]);
        $success = 'Utilisateur supprimé.';
    }
}

// Liste des utilisateurs
$users = $pdo->query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>DAT'ASBH - Gestion des utilisateurs</title>
  <link rel="icon" href="images/logo_asbh.png" />
  <script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        primary : '#292E68',
        danger  : '#A00E0F'
      },
      fontFamily:{
        sans  : ['Inter','sans-serif'],
        title : ['"Bebas Neue"','cursive'],
        button: ['Manrope','sans-serif'],
      }
    }
  }
};
</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Bebas+Neue&family=Manrope:wght@500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-[#A00E0F] via-[#5e0000] to-black text-white font-sans min-h-screen">
  <?php include 'sidebar.php'; ?>

  <main class="ml-0 md:ml-48 p-6 flex flex-col items-center space-y-6">
    <h1 class="text-2xl font-title uppercase text-center w-full">Gestion des utilisateurs</h1>

    <?php if ($errors): ?>
      <div class="bg-red-600 bg-opacity-50 text-red-100 p-4 rounded max-w-md w-full mx-auto">
        <ul class="list-disc list-inside">
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="bg-green-600 bg-opacity-50 text-green-100 p-4 rounded max-w-md w-full mx-auto">
        <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>

    <!-- Formulaire de création -->
    <form method="post" class="bg-black/60 border border-white/10 rounded-xl p-6 max-w-md w-full mx-auto space-y-4">
      <h2 class="text-lg font-semibold text-center">Créer un compte</h2>
      <div>
        <label class="block mb-1">Nom d’utilisateur</label>
        <input type="text" name="username" required
               class="w-full bg-white/20 text-white px-3 py-2 rounded" />
      </div>
      <div>
        <label class="block mb-1">Mot de passe</label>
        <input type="password" name="password" required
               class="w-full bg-white/20 text-white px-3 py-2 rounded" />
      </div>
      <button name="create" type="submit"
              class="w-full bg-primary hover:bg-[#1f2550] px-4 py-2 rounded text-white font-semibold">
        Créer
      </button>
    </form>

    <!-- Liste des utilisateurs -->
    <div class="bg-black/60 border border-white/10 rounded-xl p-6 max-w-md w-full mx-auto">
      <h2 class="text-lg font-semibold text-center mb-4">Comptes existants</h2>
      <ul class="space-y-2">
        <?php foreach ($users as $u): ?>
          <li class="flex justify-between items-center">
            <span><?= htmlspecialchars($u['username']) ?></span>
            <form method="post" onsubmit="return confirm('Supprimer <?= htmlspecialchars(addslashes($u['username'])) ?> ?');">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button name="delete" type="submit"
                      class="text-red-400 hover:text-red-600 text-sm">
                Supprimer
              </button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </main>
</body>
</html>