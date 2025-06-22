<?php
/* creer_joueur.php — formulaire en 2 colonnes, harmonie ASBH -------------- */
session_start();
require_once 'auth.php';
require_once 'db.php';
$pdo = getBD();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ── constantes upload ──────────────────────────────────────────────────── */
$maxSize = 2 * 1024 * 1024; // 2 Mo
$allowed = ['jpg', 'jpeg', 'png', 'webp'];
$uploads = __DIR__ . '/uploads/';

$err = [];

/* ── POST : validation + insertion ──────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = strtoupper(trim($_POST['nom_joueur'] ?? ''));
    $prenom = ucfirst(strtolower(trim($_POST['prenom_joueur'] ?? '')));
    $date = $_POST['date_naissance'] ?? '';
    $taille = intval($_POST['taille_cm'] ?? 0);
    $poids = intval($_POST['poids_kg'] ?? 0);
    $poste1 = $_POST['poste'] ?? '';
    $poste2 = $_POST['poste_secondaire'] ?? '';
    $photo = 'images/default_avatar.jpg';
    $idp = '0'; // Valeur initiale par défaut

    if ($nom === '') $err[] = 'Le nom est obligatoire';
    if ($prenom === '') $err[] = 'Le prénom est obligatoire';
    if ($date === '') $err[] = 'La date de naissance est obligatoire';

    /* upload facultatif */
    if (!empty($_FILES['photo']['name'])) {
        $f = $_FILES['photo'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) $err[] = 'Format image non autorisé';
        if ($f['size'] > $maxSize) $err[] = 'Image > 2 Mo';
        if (!$err) {
            $name = uniqid() . '.' . $ext;
            if (move_uploaded_file($f['tmp_name'], $uploads . $name))
                $photo = 'uploads/' . $name;
            else $err[] = "Échec d'upload";
        }
    }

    if (!$err) {
    $sql = "INSERT INTO joueur
            (nom_joueur, prenom_joueur, date_naissance, taille_cm, poids_kg,
             poste, poste_secondaire, photo_path)
            VALUES (?,?,?,?,?,?,?,?)";

    $pdo->prepare($sql)->execute([
        $nom, $prenom, $date, $taille, $poids, $poste1, $poste2, $photo
    ]);

    $_SESSION['toast'] = [
        'message' => 'Joueur créé avec succès !',
        'type' => 'success'
    ];
    header('Location: ' . "joueurs.php");
    exit;
}
}
?>

<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DAT'ASBH - Ajouter joueur</title>
    <link rel="icon" href="images/logo_asbh.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#292E68',
                        danger: '#A00E0F',
                        success: '#4CAF50' // Adding a success color for the toast
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        title: ['"Bebas Neue"', 'cursive'],
                        button: ['Manrope', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Bebas+Neue&family=Manrope:wght@500;600;700&display=swap" rel="stylesheet">
</head>

<body class="bg-gradient-to-br from-[#A00E0F] via-[#5e0000] to-black text-white font-sans min-h-screen">
    <?php include 'sidebar.php'; ?>

    <main class="ml-0 md:ml-48 flex flex-col items-center justify-center p-6 min-h-screen">
        <h1 class="font-title text-3xl md:text-4xl uppercase mb-6">Ajouter un joueur</h1>

        <?php if (!empty($_SESSION['toast'])): ?>
            <div id="toast" class="mb-4 px-4 py-2 bg-success rounded shadow text-center">
                <?= htmlspecialchars($_SESSION['toast']) ?>
            </div>
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="max-w-4xl w-full flex flex-col md:flex-row gap-8">
            <!-- Colonne infos -->
            <section class="md:flex-1 bg-black/70 border-2 border-white/60 rounded-xl p-6 shadow-lg space-y-4">
                <?php if ($err): ?>
                    <ul class="text-danger list-disc list-inside mb-2">
                        <?php foreach ($err as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; ?>
                    </ul>
                <?php endif; ?>

                <div class="flex gap-4">
                    <div class="flex-1">
                        <label class="block text-sm mb-1">Nom *</label>
                        <input name="nom_joueur" required value="<?= htmlspecialchars($_POST['nom_joueur'] ?? '') ?>" class="w-full p-2 rounded text-black">
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm mb-1">Prénom *</label>
                        <input name="prenom_joueur" required value="<?= htmlspecialchars($_POST['prenom_joueur'] ?? '') ?>" class="w-full p-2 rounded text-black">
                    </div>
                </div>

                <label class="block text-sm mb-1">Date de naissance *</label>
                <input type="date" name="date_naissance" required value="<?= htmlspecialchars($_POST['date_naissance'] ?? '') ?>" class="w-full p-2 rounded text-black">

                <div class="flex gap-4">
                    <div class="flex-1">
                        <label class="block text-sm mb-1">Taille (cm)</label>
                        <input type="number" name="taille_cm" min="0" max="250" value="<?= htmlspecialchars($_POST['taille_cm'] ?? '') ?>" class="w-full p-2 rounded text-black">
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm mb-1">Poids (kg)</label>
                        <input type="number" name="poids_kg" min="0" max="250" value="<?= htmlspecialchars($_POST['poids_kg'] ?? '') ?>" class="w-full p-2 rounded text-black">
                    </div>
                </div>

                <label class="block text-sm mb-1">Poste principal *</label>
                <select name="poste" required class="w-full p-2 rounded text-black">
                    <option value="">-- Choisir un poste --</option>
                    <?php
                    $postes = ["Pilier gauche", "Talonneur", "Pilier droit", "Deuxième ligne", "Troisième ligne", "Demi de mêlée", "Demi d'ouverture", "Centre", "Ailier", "Arrière"];
                    foreach ($postes as $poste) {
                        $sel = ($_POST['poste'] ?? '') === $poste ? 'selected' : '';
                        echo "<option $sel>$poste</option>";
                    }
                    ?>
                </select>

                <label class="block text-sm mb-1">Poste secondaire (optionnel)</label>
                <select name="poste_secondaire" class="w-full p-2 rounded text-black">
                    <option value="">-- Aucun --</option>
                    <?php
                    foreach ($postes as $poste) {
                        $sel = ($_POST['poste_secondaire'] ?? '') === $poste ? 'selected' : '';
                        echo "<option $sel>$poste</option>";
                    }
                    ?>
                </select>
            </section>

            <!-- Colonne photo + actions -->
            <aside class="md:w-80 flex flex-col items-stretch gap-4">
                <div class="bg-black/70 border-2 border-white/60 rounded-xl p-6 text-center shadow-lg space-y-4">
                    <h2 class="font-semibold">Photo de profil</h2>
                    <img id="preview" src="images/default_avatar.jpg" alt="Aperçu" class="w-40 h-40 mx-auto object-cover rounded-full border">
                    <input type="file" name="photo" id="photo" accept=".jpg,.jpeg,.png,.webp" class="block w-full text-sm text-gray-200 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-gray-200 file:text-black file:font-semibold hover:file:bg-gray-300">
                    <p class="text-xs text-gray-300">JPG, PNG, WebP – 2 Mo max (facultatif)</p>
                </div>

                <button type="submit" class="bg-primary hover:bg-primaryDark text-white font-semibold py-2 rounded shadow">
                    Ajouter joueur
                </button>
                <a href="joueurs.php" class="text-center bg-danger hover:bg-red-800 py-2 rounded shadow">Retour</a>
            </aside>
        </form>
    </main>

    <script>
        document.getElementById('photo').addEventListener('change', function(e) {
            const f = e.target.files[0];
            if (f) document.getElementById('preview').src = URL.createObjectURL(f);
        });

        // JavaScript to hide the toast after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const toast = document.getElementById('toast');
            if (toast) {
                setTimeout(function() {
                    toast.style.display = 'none';
                }, 5000);
            }
        });
    </script>
</body>
</html>
