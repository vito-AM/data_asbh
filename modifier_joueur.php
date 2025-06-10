<?php
/* modifier_joueur.php — Édition d’un joueur ASBH (mise à jour) */
session_start();
require_once 'db.php';
require_once 'auth.php';
$pdo = getBD();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
ini_set('display_errors', 1);
error_reporting(E_ALL);

$id = intval($_GET['id'] ?? 0);
if (!$id) {
  header('Location: joueurs.php');
  exit;
}

/* ── Récupération des infos du joueur ──────────────────────────────────── */
$stmt = $pdo->prepare("SELECT * FROM joueur WHERE id_joueur = ?");
$stmt->execute([$id]);
$joueur = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$joueur) {
  header('Location: joueurs.php');
  exit;
}

// Préremplir poste et poste_secondaire
$poste        = $joueur['poste'] ?? '';
$posteSecond  = $joueur['poste_secondaire'] ?? '';
$activite     = $joueur['activite'] ?? 'actif';

$maxSize = 2 * 1024 * 1024; // 2 Mo
$allowed = ['jpg','jpeg','png','webp'];
$uploads = __DIR__ . '/uploads/';
$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nom              = trim($_POST['nom'] ?? '');
  $prenom           = trim($_POST['prenom'] ?? '');
  $date             = $_POST['date_naissance'] ?? '';
  $taille           = intval($_POST['taille_cm'] ?? 0);
  $poids            = intval($_POST['poids_kg'] ?? 0);
  $posteN           = trim($_POST['poste'] ?? '');
  $posteSecondN     = trim($_POST['poste_secondaire'] ?? '');
  $activiteN        = in_array($_POST['activite'] ?? '', ['actif','inactif'])
                      ? $_POST['activite'] : 'actif';
  $photoPath        = $joueur['photo_path'];

  // Validation
  if ($nom === '')      $err[] = 'Le nom est obligatoire';
  if ($prenom === '')   $err[] = 'Le prénom est obligatoire';
  if ($date === '')     $err[] = 'La date de naissance est obligatoire';
  if ($posteN === '')   $err[] = 'Le poste est obligatoire';

  // Upload optionnel
  if (!empty($_FILES['photo']['name'])) {
    $f   = $_FILES['photo'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
      $err[] = 'Format d’image non autorisé (jpg, jpeg, png, webp)';
    }
    if ($f['size'] > $maxSize) {
      $err[] = 'La photo dépasse 2 Mo maximum';
    }
    if (empty($err)) {
      $newName = uniqid('joueur_') . '.' . $ext;
      $dest    = $uploads . $newName;
      if (!move_uploaded_file($f['tmp_name'], $dest)) {
        $err[] = 'Erreur lors de l’enregistrement de la photo';
      } else {
        // Suppression de l’ancienne photo si existante
        if (!empty($joueur['photo_path']) && file_exists(__DIR__ . '/' . $joueur['photo_path'])) {
          @unlink(__DIR__ . '/' . $joueur['photo_path']);
        }
        $photoPath = 'uploads/' . $newName;
      }
    }
  }

  if (empty($err)) {
    $sql = "
      UPDATE joueur
      SET
        nom_joueur        = ?,
        prenom_joueur     = ?,
        date_naissance    = ?,
        taille_cm         = ?,
        poids_kg          = ?,
        poste             = ?,
        poste_secondaire  = ?,
        activite          = ?,
        photo_path        = ?
      WHERE id_joueur = ?
    ";
    $pdo->prepare($sql)->execute([
      $nom,
      $prenom,
      $date,
      $taille,
      $poids,
      $posteN,
      $posteSecondN,
      $activiteN,
      $photoPath,
      $id
    ]);
    header('Location: joueur.php?id=' . $id);
    exit;
  }
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Modifier joueur – ASBH</title>
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
<body class="bg-gradient-to-br from-[#A00E0F] via-[#5e0000] to-black text-white font-sans min-h-screen">
  <?php include 'sidebar.php'; ?>

  <main class="ml-0 md:ml-48 flex flex-col items-center justify-center p-6 min-h-screen">
    <h1 class="font-title text-3xl md:text-4xl uppercase mb-6">Modifier un joueur</h1>

    <?php if (!empty($err)) : ?>
      <ul class="text-danger list-disc list-inside mb-4">
        <?php foreach ($err as $e) : ?>
          <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="max-w-4xl w-full flex flex-col md:flex-row gap-8">

      <!-- SECTION DONNÉES JOUEUR -->
      <section class="md:flex-1 bg-black/70 border-2 border-white/60 rounded-xl p-6 shadow-lg space-y-4">
        <div class="flex gap-4">
          <div class="flex-1">
            <label class="block text-sm mb-1" for="nom">Nom *</label>
            <input
              id="nom" name="nom" type="text" required
              value="<?= htmlspecialchars($joueur['nom_joueur']) ?>"
              class="w-full p-2 rounded text-black"
            >
          </div>
          <div class="flex-1">
            <label class="block text-sm mb-1" for="prenom">Prénom *</label>
            <input
              id="prenom" name="prenom" type="text" required
              value="<?= htmlspecialchars($joueur['prenom_joueur']) ?>"
              class="w-full p-2 rounded text-black"
            >
          </div>
        </div>

        <label class="block text-sm mb-1" for="date_naissance">Date de naissance *</label>
        <input
          id="date_naissance" name="date_naissance" type="date" required
          value="<?= htmlspecialchars($joueur['date_naissance']) ?>"
          class="w-full p-2 rounded text-black"
        >

        <div class="flex gap-4">
          <div class="flex-1">
            <label class="block text-sm mb-1" for="taille_cm">Taille (cm)</label>
            <input
              id="taille_cm" name="taille_cm" type="number" min="100" max="250"
              value="<?= htmlspecialchars($joueur['taille_cm']) ?>"
              class="w-full p-2 rounded text-black"
            >
          </div>
          <div class="flex-1">
            <label class="block text-sm mb-1" for="poids_kg">Poids (kg)</label>
            <input
              id="poids_kg" name="poids_kg" type="number" min="30" max="250"
              value="<?= htmlspecialchars($joueur['poids_kg']) ?>"
              class="w-full p-2 rounded text-black"
            >
          </div>
        </div>

        <label class="block text-sm mb-1" for="poste">Poste *</label>
        <input
          id="poste" name="poste" type="text" required
          value="<?= htmlspecialchars($poste) ?>"
          class="w-full p-2 rounded text-black"
        >

        <label class="block text-sm mb-1" for="poste_secondaire">Poste secondaire (optionnel)</label>
        <input
          id="poste_secondaire" name="poste_secondaire" type="text"
          value="<?= htmlspecialchars($posteSecond) ?>"
          class="w-full p-2 rounded text-black"
        >

        <!-- NOUVEAU : statut Activité -->
        <label class="block text-sm mb-1" for="activite">Statut activité *</label>
        <select id="activite" name="activite" required class="w-full p-2 rounded text-black">
          <option value="actif"    <?= $activite === 'actif'    ? 'selected' : '' ?>>Actif</option>
          <option value="inactif"  <?= $activite === 'inactif'  ? 'selected' : '' ?>>Inactif</option>
        </select>
      </section>

      <!-- SECTION PHOTO & BOUTONS -->
      <aside class="md:w-80 flex flex-col items-stretch gap-4">
        <div class="bg-black/70 border-2 border-white/60 rounded-xl p-6 text-center shadow-lg space-y-4">
          <h2 class="font-semibold">Photo de profil</h2>
          <img
            id="preview"
            src="<?= htmlspecialchars($joueur['photo_path'] ?? 'images/default_avatar.jpg') ?>"
            alt="Aperçu"
            class="w-40 h-40 mx-auto object-cover rounded-full border"
          >
          <input
            type="file" name="photo" id="photo" accept=".jpg,.jpeg,.png,.webp"
            class="block w-full text-sm text-gray-200 file:mr-4 file:py-2 file:px-4
                   file:rounded file:border-0 file:bg-gray-200
                   file:text-black file:font-semibold hover:file:bg-gray-300"
          >
          <p class="text-xs text-gray-300">JPG, PNG, WebP – 2 Mo max</p>
        </div>

        <button type="submit"
                class="bg-primary hover:bg-primaryDark text-white font-semibold py-2 rounded shadow">
          Mettre à jour
        </button>
        <a href="joueur.php?id=<?= htmlspecialchars($joueur['id_joueur']) ?>"
           class="text-center bg-danger hover:bg-red-800 py-2 rounded shadow">
          Annuler
        </a>
      </aside>
    </form>
  </main>

  <script>
    document.getElementById('photo').addEventListener('change', e => {
      const f = e.target.files[0];
      if (f) document.getElementById('preview').src = URL.createObjectURL(f);
    });
  </script>
</body>
</html>
