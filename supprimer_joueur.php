<?php
// supprimer_joueur.php — supprime un joueur avec confirmation
require_once 'db.php';
require_once 'auth.php';
$pdo = getBD();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$id = intval($_GET['id'] ?? 0);
if (!$id) {
  header('Location: joueurs.php');
  exit;
}

// Vérification existence joueur
$stmt = $pdo->prepare("SELECT nom_joueur, prenom_joueur FROM joueur WHERE id = ?");
$stmt->execute([$id]);
$joueur = $stmt->fetch();
if (!$joueur) {
  header('Location: joueurs.php');
  exit;
}

// Suppression validée
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $pdo->prepare("DELETE FROM joueur WHERE id = ?")->execute([$id]);
  header('Location: joueurs.php');
  exit;
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Supprimer joueur</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-red-900 via-black to-black text-white font-sans min-h-screen flex items-center justify-center">
  <div class="bg-black/70 border border-white/20 p-8 rounded-xl shadow-lg max-w-md w-full">
    <h1 class="text-xl font-bold mb-4">Supprimer le joueur ?</h1>
    <p class="mb-6">Es-tu sûr de vouloir supprimer <strong><?= htmlspecialchars($joueur['prenom_joueur']) ?> <?= htmlspecialchars($joueur['nom_joueur']) ?></strong> ?</p>

    <form method="post" class="flex justify-between">
      <a href="joueur.php?id=<?= $id ?>" class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded">Annuler</a>
      <button type="submit" class="px-4 py-2 bg-danger hover:bg-red-700 text-white rounded">Supprimer</button>
    </form>
  </div>
</body>
</html>
