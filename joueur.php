<?php
/* joueur.php – fiche joueur ASBH (v2) */
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

/* ───────────────── INFOS JOUEUR + IDP ───────────────── */
$sql = "
  SELECT  j.id_joueur,
          j.nom_joueur,
          j.prenom_joueur,
          j.photo_path,
          j.poste,
          j.poste_secondaire,
          j.date_naissance,
          j.taille_cm,
          j.poids_kg,
          COALESCE(i.idp,0) AS idp
  FROM    joueur AS j
  LEFT JOIN (
      SELECT id_joueur, AVG(idp) AS idp
      FROM   idp
      GROUP  BY id_joueur
  ) AS i ON i.id_joueur = j.id_joueur
  WHERE   j.id_joueur = ?
";
$player = $pdo->prepare($sql);
$player->execute([$id]);
$player = $player->fetch(PDO::FETCH_ASSOC);
if (!$player) {
  header('Location: joueurs.php');
  exit;
}

/* ───────────────── HISTORIQUE DES MATCHS ───────────────── */
$matchesStmt = $pdo->prepare("
  SELECT
      m.id_match,
      m.locaux,
      m.date,
      m.journee,
      m.visiteurs,
      CONCAT(m.score_locaux, '–', m.score_visiteurs) AS score,
      i.minutes_jouees,
      i.idp
  FROM   idp AS i
  JOIN   `match` AS m ON m.id_match = i.id_match
  WHERE  i.id_joueur = ?
  ORDER BY m.date DESC
");

$matchesStmt->execute([$id]);
$matches = $matchesStmt->fetchAll(PDO::FETCH_ASSOC);

function age($d) { return (new DateTime($d))->diff(new DateTime())->y; }

$evoLabels   = [];
$minutesData = [];
$idpData     = [];
foreach (array_reverse($matches) as $m) {
    // On affiche la journée si dispo, sinon la date
    $evoLabels[]   = $m['journee'] ?: date('d/m', strtotime($m['date']));
    $minutesData[] = (int) $m['minutes_jouees'];
    $idpData[]     = (float) $m['idp'];
}

/*  Radar : moyenne des coefficients “details” sur les 5 derniers matches */
$coeffs = ['spec'=>0,'attaque'=>0,'defense'=>0,'discipline'=>0,'engagement'=>0,'initiative'=>0];
$rows   = $pdo->prepare("SELECT details
                         FROM   idp
                         WHERE  id_joueur = ? AND details IS NOT NULL
                         ORDER  BY id_idp DESC LIMIT 5");
$rows->execute([$id]);
$cnt = 0;
foreach ($rows->fetchAll(PDO::FETCH_COLUMN) as $json) {
    $d = json_decode($json, true);
    if (!$d) continue;
    foreach ($coeffs as $k => $v) if (isset($d[$k])) $coeffs[$k] += $d[$k];
    $cnt++;
}
if ($cnt) foreach ($coeffs as $k => $v) $coeffs[$k] = round($v / $cnt, 1);
?>
<script>
const evoLabels   = <?= json_encode($evoLabels) ?>;
const minutesData = <?= json_encode($minutesData) ?>;
const idpData     = <?= json_encode($idpData) ?>;

const radarLabels = <?= json_encode(array_map('ucfirst', array_keys($coeffs))) ?>;
const radarData   = <?= json_encode(array_values($coeffs)) ?>;
</script>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($player['prenom_joueur'].' '.$player['nom_joueur']) ?> – ASBH</title>
  <!-- Tailwind -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { theme: { extend: { colors: { primary:'#292E68', danger:'#A00E0F' }, fontFamily: { sans:['Inter'], title:['\"Bebas Neue\"'], button:['Manrope'] } } } };
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Bebas+Neue&family=Manrope:wght@500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0"></script>
</head>
<body class="bg-gradient-to-br from-[#A00E0F] via-[#5e0000] to-black text-white font-sans min-h-screen">
<?php include 'sidebar.php'; ?>
<main class="ml-0 md:ml-48 px-6 pt-8 pb-20 space-y-10">
  <!-- EN-TÊTE -->
  <div class="flex flex-col md:flex-row items-center gap-6">
    <img src="<?= htmlspecialchars($player['photo_path']) ?>" alt="Photo de <?= htmlspecialchars($player['prenom_joueur'].' '.$player['nom_joueur']) ?>" class="w-40 h-40 md:w-48 md:h-48 object-cover rounded-full border-4 border-white/20 shadow-lg">
    <div class="flex-1 flex flex-col justify-center">
      <h1 class="font-title text-4xl md:text-5xl leading-none"><?= ucfirst(htmlspecialchars($player['prenom_joueur'])) ?> <span class="uppercase"><?= strtoupper(htmlspecialchars($player['nom_joueur'])) ?></span></h1>
      <div class="flex gap-6 mt-2 text-sm text-gray-300">
        <span><strong>Poste&nbsp;:</strong> <?= htmlspecialchars($player['poste']) ?><?php if ($player['poste_secondaire']): ?> / <?= htmlspecialchars($player['poste_secondaire']) ?><?php endif; ?></span>
        <span><strong>Âge&nbsp;:</strong> <?= age($player['date_naissance']) ?> ans</span>
      </div>
    </div>
  </div>

  <!-- CARTES INFOS -->
  <div class="grid gap-6 md:grid-cols-3">
    <div class="bg-black/60 border border-white/10 rounded-xl p-6 shadow-lg">
      <h2 class="text-lg font-semibold text-gray-300 mb-2">Informations</h2>
      <ul class="space-y-1 text-sm text-gray-200">
        <li><strong>Nationalité&nbsp;:</strong> France</li>
        <li><strong>Date de naissance&nbsp;:</strong> <?= htmlspecialchars($player['date_naissance']) ?></li>
        <li><strong>Taille&nbsp;:</strong> <?= (int)$player['taille_cm'] ?> cm</li>
        <li><strong>Poids&nbsp;:</strong> <?= (int)$player['poids_kg'] ?> kg</li>
      </ul>
    </div>
    <div class="bg-black/60 border border-white/10 rounded-xl flex flex-col items-center p-6 shadow-lg">
      <?php $ip = (int)$player['idp']; $pct = max(0,min($ip,100)); ?>
      <div class="relative w-32 h-32" style="background:conic-gradient(#A00E0F <?= $pct ?>%, rgba(255,255,255,.1) <?= $pct ?>% 100%); border-radius:9999px;">
        <span class="absolute inset-0 flex flex-col items-center justify-center font-bold text-3xl"><?= $ip ?><span class="text-xs tracking-wider text-gray-300">IDP</span></span>
      </div>
    </div>
    <div class="bg-black/60 border border-white/10 rounded-xl p-6 shadow-lg">
      <h2 class="text-lg font-semibold text-gray-300 mb-2">Badges</h2>
      <ul class="space-y-2 text-sm">
        <li class="flex items-center gap-2"><img src="images/badge_gold.svg" class="w-5"> Contest</li>
        <li class="flex items-center gap-2"><img src="images/badge_gold.svg" class="w-5"> Casseur de plaquages</li>
        <li class="flex items-center gap-2"><img src="images/badge_gold.svg" class="w-5"> Soutiens offensifs</li>
      </ul>
    </div>
  </div>

  <!-- HISTORIQUE + DÉTAILS -->
  <div class="grid gap-6 md:grid-cols-4">
    <aside class="bg-black/60 border border-white/10 rounded-xl p-4 h-[500px] overflow-y-auto">
      <h2 class="text-lg font-semibold text-gray-300 mb-2">Historique des matchs</h2>
      <?php foreach ($matches as $m): ?>
  <button 
    data-match="<?= $m['id_match'] ?>"
    class="match-btn w-full flex justify-between items-center px-3 py-2 bg-white/5 hover:bg-white/10 rounded mb-2"
  >
    <div class="flex flex-col text-left">
      <span class="font-semibold text-sm">
<span class="font-semibold text-sm">
  <?= date('d/m/Y', strtotime($m['date'])) ?> –
  <?= htmlspecialchars($m['locaux']) ?>
  <?= htmlspecialchars($m['score']) ?>
  <?= htmlspecialchars($m['visiteurs']) ?>
</span>

      </span>
    </div>
    <div class="flex items-center gap-2">
      <span class="text-sm"><?= (int)$m['minutes_jouees'] ?>’</span>
      <div class="w-8 h-8 rounded-full bg-danger flex items-center justify-center text-sm font-bold">
        <?= round($m['idp']) ?>
      </div>
    </div>
  </button>
<?php endforeach; ?>

    </aside>
    <section id="match-details" class="md:col-span-3 bg-black/60 border border-white/10 rounded-xl p-4 h-[500px] overflow-y-auto"></section>
  </div>

  <!-- GRAPHIQUES -->
  <div class="grid gap-6 md:grid-cols-2">
    <div class="bg-black/60 border border-white/10 rounded-xl p-6 shadow-lg"><h2 class="text-lg font-semibold text-gray-300 mb-2">Évolution Temps de jeu & IDP</h2><canvas id="evolutionChart"></canvas></div>
    <div class="bg-black/60 border border-white/10 rounded-xl p-6 shadow-lg"><h2 class="text-lg font-semibold text-gray-300 mb-2">Stats Principales (Radar)</h2><canvas id="radarChart"></canvas></div>
  </div>

  <!-- ACTIONS ADMIN -->
  <div class="flex gap-4 justify-end">
    <a href="modifier_joueur.php?id=<?= $player['id_joueur'] ?>" class="bg-white/20 hover:bg-primary px-4 py-2 rounded">Modifier</a>
  </div>

  <a href="joueurs.php" class="fixed bottom-6 right-6 bg-white/20 hover:bg-primary px-4 py-2 rounded-full backdrop-blur-sm">↩ Liste</a>
</main>

<script>
// ───────── détail du match (déjà présent) ─────────
document.addEventListener('DOMContentLoaded', () => {
  /* —— navigation historique —— */
  const buttons = document.querySelectorAll('.match-btn');
  const details = document.getElementById('match-details');
  buttons.forEach(btn => btn.addEventListener('click', async () => {
      buttons.forEach(b => b.classList.remove('ring-2','ring-primary'));
      btn.classList.add('ring-2','ring-primary');
      const html = await (await fetch(`match_details.php?player=<?=$id?>&match=${btn.dataset.match}`)).text();
      details.innerHTML = html;
  }));
  if (buttons.length) buttons[0].click();

/* ───────── GRAPHIQUE 1 : Evolution ───────── */
const ctxEvo = document.getElementById('evolutionChart').getContext('2d');

new Chart(ctxEvo, {
  data: {
    labels: evoLabels,
    datasets: [
      {                       // barres — dessinées en premier
        type: 'bar',
        order: 1,             // > 0  ➜ couche du dessous
        label: 'Temps de jeu en min',
        data: minutesData,
        borderRadius: 3,
        backgroundColor: (ctx) => {
          const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, 240);
          g.addColorStop(0, '#A00E0F');
          g.addColorStop(1, '#400000');
          return g;
        }
      },
      {                       // ligne — dessinée en dernier
        type: 'line',
        order: 0,             // plus petit ➜ couche du dessus
        label: 'Indice de performance',
        data: idpData,
        borderColor: '#fff',
        backgroundColor: '#fff',
        pointRadius: 3,
        tension: 0.35
      }
    ]
  },
  options: {
    responsive: true,
    scales: {
      y: { beginAtZero: true, max: 100, grid: { color: 'rgba(255,255,255,0.1)' } }
    },
    plugins: {
      legend: { labels: { color: '#ddd', boxWidth: 12 } },
      tooltip: { mode: 'index', intersect: false }
    }
  }
});


  /* ───────── GRAPHIQUE 2 : Radar profil ───────── */
  new Chart(document.getElementById('radarChart'), {
    type: 'radar',
    data: {
      labels: radarLabels,
      datasets: [{
        data: radarData,
        backgroundColor: 'rgba(160,14,15,.45)',
        borderColor: '#A00E0F',
        pointBackgroundColor:'#fff',
        borderWidth:2
      }]
    },
    options: {
      scales: {
        r: {
          beginAtZero:true,
          grid:{color:'rgba(255,255,255,.1)'},
          angleLines:{color:'rgba(255,255,255,.1)'},
          pointLabels:{color:'#fff', font:{size:11}},
          ticks:{display:false}
        }
      },
      plugins:{legend:{display:false}}
    }
  });
});
</script>
<script src="js/chat-widget.js" defer></script>
</body>
</html>
