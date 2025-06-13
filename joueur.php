<?php
/* joueur.php – fiche complète ASBH, style dashboard sombre */
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

// Suppression du joueur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer'])) {
  $pdo->prepare("DELETE FROM joueur WHERE id_joueur = ?")->execute([$id]);
  $_SESSION['toast'] = [
      'message' => 'Joueur supprimé avec succès !',
      'type' => 'error' 
  ];
  header('Location: joueurs.php');
  exit;
}



// Récupération du joueur
$sql = "
  SELECT 
    j.id_joueur,
    j.nom_joueur,
    j.prenom_joueur,
    j.photo_path,
    j.poste,
    j.poste_secondaire,
    i.idp,
    j.date_naissance,
    j.taille_cm,
    j.poids_kg
  FROM joueur AS j
  LEFT JOIN idp AS i
    ON i.id_joueur = j.id_joueur
  WHERE j.id_joueur = ?
";
$playerStmt = $pdo->prepare($sql);
$playerStmt->execute([$id]);
$player = $playerStmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
  header('Location: joueurs.php');
  exit;
}

function age($d) {
  return (new DateTime($d))->diff(new DateTime())->y;
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($player['prenom_joueur'] . ' ' . $player['nom_joueur']) ?> – ASBH</title>
  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#292E68',
            danger:  '#A00E0F'
          },
          fontFamily: {
            sans: ['Inter'],
            title: ['"Bebas Neue"'],
            button: ['Manrope']
          }
        }
      }
    };
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Bebas+Neue&family=Manrope:wght@500;600;700&display=swap" rel="stylesheet">
  <!-- Chart.js pour les graphiques -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0"></script>
</head>
<body class="bg-gradient-to-br from-[#A00E0F] via-[#5e0000] to-black text-white font-sans min-h-screen">
  <?php include 'sidebar.php'; ?>

  <main class="ml-0 md:ml-48 px-6 pt-8 pb-20 space-y-10">

    <!-- ── EN‐TÊTE : PHOTO + NOM + INFORMATIONS ── -->
    <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
      <!-- Photo du joueur (ou placeholder) -->
      <div class="flex-shrink-0">
          <img
            src="<?= htmlspecialchars($player['photo_path']) ?>"
            alt="Photo de <?= htmlspecialchars($player['prenom_joueur'] . ' ' . $player['nom_joueur']) ?>"
            class="w-40 h-40 md:w-48 md:h-48 object-cover rounded-full border-4 border-white/20 shadow-lg"
          >
      </div>

      <!-- Nom + Poste + Âge + Date naiss. + Taille + Poids -->
      <div class="flex-1 space-y-2">
        <h1 class="font-title text-4xl md:text-5xl tracking-wide">
          <?= ucfirst(htmlspecialchars($player['prenom_joueur'])) ?>
          <span class="uppercase"><?= strtoupper(htmlspecialchars($player['nom_joueur'])) ?></span>
        </h1>
        <div class="flex flex-wrap items-center gap-4 text-sm text-gray-300">
          <span><strong>Poste&nbsp;:</strong>
            <?= htmlspecialchars($player['poste']) ?>
            <?php if (!empty($player['poste_secondaire'])): ?>
              &nbsp;/&nbsp;<?= htmlspecialchars($player['poste_secondaire']) ?>
            <?php endif; ?>
          </span>
          <span><strong>Âge&nbsp;:</strong> <?= age($player['date_naissance']) ?> ans</span>
          <span><strong>Naissance&nbsp;:</strong> <?= htmlspecialchars($player['date_naissance']) ?></span>
          <span><strong>Taille&nbsp;:</strong> <?= (int)$player['taille_cm'] ?> cm</span>
          <span><strong>Poids&nbsp;:</strong> <?= (int)$player['poids_kg'] ?> kg</span>
        </div>
      </div>

      <!-- Cercle IDP -->
      <?php $ip = (int)$player['idp']; $pct = max(0, min($ip, 100)); ?>
      <div class="mt-4 md:mt-0">
        <div class="relative w-32 h-32 md:w-36 md:h-36 mx-auto" style="background:conic-gradient(#A00E0F <?= $pct ?>%, rgba(255,255,255,.1) <?= $pct ?>% 100%); border-radius:9999px;">
          <span class="absolute inset-0 flex flex-col items-center justify-center font-bold text-3xl md:text-4xl">
            <?= $ip ?><span class="text-xs mt-1 uppercase tracking-wider text-gray-300">IDP</span>
          </span>
        </div>
      </div>
    </div>

    <!-- ── CARTE INFOS / ÉQUIPE / BADGES ── -->
    <div class="grid gap-6 md:grid-cols-3">
      <!-- Bloc Informations -->
      <div class="bg-black/60 border border-white/10 rounded-xl p-6 shadow-lg space-y-4">
        <h2 class="text-lg font-semibold text-gray-300 mb-2">Informations</h2>
        <ul class="space-y-1 text-sm">
          <li><span class="text-gray-400">Nationalité&nbsp;:</span> France</li>
          <li><span class="text-gray-400">Âge&nbsp;:</span> <?= age($player['date_naissance']) ?> ans</li>
          <li><span class="text-gray-400">Date de naissance&nbsp;:</span> <?= htmlspecialchars($player['date_naissance']) ?></li>
          <li><span class="text-gray-400">Taille&nbsp;:</span> <?= (int)$player['taille_cm'] ?> cm</li>
          <li><span class="text-gray-400">Poids&nbsp;:</span> <?= (int)$player['poids_kg'] ?> kg</li>
        </ul>
      </div>

      <!-- Bloc Équipe -->
      <div class="bg-black/60 border border-white/10 rounded-xl flex flex-col items-center justify-center p-6 shadow-lg">
        <h2 class="text-lg font-semibold text-gray-300 mb-4">Équipe</h2>
        <div class="flex items-center gap-3">
          <img src="images/logo_club_placeholder.png" alt="Club" class="w-12 h-12 object-contain">
          <div class="text-left">
            <p class="text-sm uppercase text-gray-400">Pro&nbsp;D2</p>
            <p class="text-base font-bold">AS Béziers Hérault</p>
          </div>
        </div>
      </div>

      <!-- Bloc Badges -->
      <div class="bg-black/60 border border-white/10 rounded-xl p-6 shadow-lg space-y-4">
        <h2 class="text-lg font-semibold text-gray-300 mb-2">Badges</h2>
        <ul class="space-y-2 text-sm">
          <li class="flex items-center gap-2"><img src="images/badge_gold.svg" class="w-5"> Contest</li>
          <li class="flex items-center gap-2"><img src="images/badge_gold.svg" class="w-5"> Casseur de plaquages</li>
          <li class="flex items-center gap-2"><img src="images/badge_gold.svg" class="w-5"> Soutiens offensifs</li>
        </ul>
      </div>
    </div>

    <!-- ── GRAPHIQUES & ÉVALUATIONS ── -->
    <div class="grid gap-6 md:grid-cols-2">
      <!-- Graphique évolution IDP -->
      <div class="bg-black/60 border border-white/10 rounded-xl p-6 shadow-lg">
        <div class="flex items-center justify-between mb-3">
          <h3 class="uppercase text-sm font-semibold text-gray-300">Évolution IDP</h3>
          <select class="text-black text-xs rounded px-1 py-0.5 bg-gray-100">
            <option>Top14</option>
            <option>Pro&nbsp;D2</option>
          </select>
        </div>
        <canvas id="ipChart" height="160"></canvas>
      </div>

      <!-- Critères d’évaluation -->
      <div class="bg-black/60 border border-white/10 rounded-xl p-6 shadow-lg">
        <h3 class="uppercase text-sm font-semibold text-gray-300 mb-3">Critères d’évaluation</h3>
        <ul class="space-y-2 text-sm">
          <?php
            $items = ["Passes","Contacts","Offloads","Défenseurs battus","Franchissements","Mètres parcourus","Soutiens offensifs","Contests","Discipline","Plaquages","Mêlées"];
            foreach ($items as $idx => $lib) {
              $star = ($idx % 5) + 1;
          ?>
            <li class="flex items-center justify-between">
              <span><?= $lib ?></span>
              <span><?php for ($i = 1; $i <= 5; $i++) echo ($i <= $star ? '★' : '☆'); ?></span>
            </li>
          <?php } ?>
        </ul>
      </div>
    </div>

    <!-- ── RADAR PROFIL ── -->
    <div class="bg-black/60 border border-white/10 rounded-xl p-6 shadow-lg w-full max-w-3xl">
      <h3 class="uppercase text-sm font-semibold text-gray-300 mb-3">Profil détaillé</h3>
      <canvas id="radarChart" height="180"></canvas>
    </div>

    <!-- ── ACTIONS : Modifier, Supprimer, Retour Liste ── -->
    <div class="flex gap-4 justify-end">
      <a href="modifier_joueur.php?id=<?= $player['id_joueur'] ?>"
         class="bg-white/20 hover:bg-primary px-4 py-2 rounded text-white">
        Modifier joueur
      </a>
      <form method="post" action="joueur.php?id=<?= $player['id_joueur'] ?>" onsubmit="return confirm('Confirmer la suppression de ce joueur ?');">
        <input type="hidden" name="supprimer" value="1">
        <button type="submit"
                class="bg-danger hover:bg-red-700 text-white font-semibold px-4 py-2 rounded shadow transition">
          🗑 Supprimer joueur
        </button>
      </form>
    </div>

    <!-- Bouton retour vers la liste -->
    <a href="joueurs.php"
       class="fixed bottom-6 right-6 bg-white/20 hover:bg-primary px-4 py-2 rounded-full text-white shadow-lg backdrop-blur-sm">
      ↩ Liste
    </a>
  </main>

  <script>
    // Graphique évolution IDP (bar + ligne)
    const ctx = document.getElementById('ipChart');
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: ['J23','J24','J25','J26','Barr','Acc','1/2'],
        datasets: [
          {
            label: 'Temps de jeu (min)',
            data: [50,50,30,40,30,60,0],
            backgroundColor: '#A00E0F'
          },
          {
            label: 'Indice',
            data: [90,88,90,89,40,80,70],
            type: 'line',
            borderColor: '#fff',
            tension: 0.3
          }
        ]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { labels: { color: '#fff' } }
        },
        scales: {
          x: { grid: { color: '#333' }, ticks: { color: '#fff' } },
          y: { grid: { color: '#333' }, ticks: { color: '#fff' } }
        }
      }
    });

    // Graphique radar du profil
    const rad = document.getElementById('radarChart');
    new Chart(rad, {
      type: 'radar',
      data: {
        labels: ['Passes','Contacts','Franchissements','Mètres parcourus','Contest','Soutiens'],
        datasets: [{
          label: 'Score',
          data: [70,65,80,60,75,50],
          fill: true,
          backgroundColor: 'rgba(160,14,15,0.4)',
          borderColor: '#A00E0F',
          pointBackgroundColor: '#fff'
        }]
      },
      options: {
        plugins: { legend: { display: false } },
        scales: {
          r: {
            angleLines: { color: '#333' },
            grid: { color: '#333' },
            pointLabels: { color: '#fff' },
            ticks: { display: false, max: 100, min: 0 }
          }
        }
      }
    });
  </script>
</body>
</html>
