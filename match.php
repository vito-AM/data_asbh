<?php
session_start();
require_once 'db.php';
require_once 'auth.php';
$pdo = getBD();

// 1. Vérification de l'ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID de match invalide.');
}

$id = (int)$_GET['id'];

// 2. Requête principale avec perf_score
$sql = "
SELECT 
    m.score_locaux,
    m.score_visiteurs,
    m.locaux,
    m.visiteurs,
    m.date,
    m.journee,
    m.competition,
    s.id_equipe,
    s.essais,
    s.transformations,
    s.drops,
    s.drops_tentes,
    s.penalites,
    s.penalites_tentees,
    el.id_equipe AS id_locaux,
    ev.id_equipe AS id_visiteurs,
    m.id_match
FROM `match` m
JOIN equipe el ON el.nom_equipe = m.locaux
JOIN equipe ev ON ev.nom_equipe = m.visiteurs
JOIN score s ON s.id_match = m.id_match
WHERE m.id_match = :id
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows || count($rows) < 2) {
    die("Match introuvable ou données incomplètes.");
}

// Extraire les données générales du match
$match = $rows[0];

// Identifier les scores des deux équipes
foreach ($rows as $row) {
    if ($row['id_equipe'] == $row['id_locaux']) {
        $score_locaux = $row;
    } elseif ($row['id_equipe'] == $row['id_visiteurs']) {
        $score_visiteurs = $row;
    }
}

// fonction de sécurité
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$sqlPointsParAction = "
SELECT 
    p.id_equipe,
    e.nom_equipe,
    p.actions,
    p.points_total,
    p.points_positifs,
    p.points_neutres,
    p.points_negatifs
FROM points p
JOIN equipe e ON p.id_equipe = e.id_equipe
WHERE p.id_match = :id
ORDER BY e.nom_equipe, p.actions
";

$stmtPoints = $pdo->prepare($sqlPointsParAction);
$stmtPoints->execute(['id' => $id]);
$pointsParAction = $stmtPoints->fetchAll(PDO::FETCH_ASSOC);

$actionsData = [];

foreach ($pointsParAction as $row) {
    $action = $row['actions'];
    $equipe = $row['nom_equipe'];
    
    if (!isset($actionsData[$action])) {
        $actionsData[$action] = [];
    }

    $actionsData[$action][$equipe] = [
        'total' => $row['points_total'],
        'positifs' => $row['points_positifs'],
        'neutres' => $row['points_neutres'],
        'negatifs' => $row['points_negatifs'],
    ];
}

$sqlTemps = "
SELECT temps_effectif_beziers, temps_effectif_equipe_adverse, temps_effectif_total
FROM temps_effectif
JOIN `match` m ON m.id_temps_effectif = temps_effectif.id_temps_effectif
WHERE m.id_match = :id
";

$stmtTemps = $pdo->prepare($sqlTemps);
$stmtTemps->execute(['id' => $id]);
$tempsEffectif = $stmtTemps->fetch(PDO::FETCH_ASSOC);

function convertToSeconds(string $time): int {
    list($hours, $minutes, $seconds) = explode(':', $time);
    return ($hours * 3600) + ($minutes * 60) + $seconds;
}

$pourcentage_beziers = $pourcentage_adverse = 0;

if (
    $tempsEffectif &&
    !empty($tempsEffectif['temps_effectif_total']) &&
    $tempsEffectif['temps_effectif_total'] !== '00:00:00'
) {
    $total_sec = convertToSeconds($tempsEffectif['temps_effectif_total']);
    $beziers_sec = convertToSeconds($tempsEffectif['temps_effectif_beziers']);
    $adverse_sec = convertToSeconds($tempsEffectif['temps_effectif_equipe_adverse']);

    if ($total_sec > 0) {
        $pourcentage_beziers = round(($beziers_sec / $total_sec) * 100);
        $pourcentage_adverse = round(($adverse_sec / $total_sec) * 100);
    }
}

$sqlTempsmt1 = "
select possession_mt_1.possession_mt_1_beziers, possession_mt_1.possession_mt_1_equipe_adverse, possession_mt_1.possession_mt_1_total
from possession_mt_1
join `match` m ON m.id_possession_mt_1=possession_mt_1.id_possession_mt_1 
where m.id_match = :id
";

$stmtTempsMT1 = $pdo->prepare($sqlTempsmt1);
$stmtTempsMT1->execute(['id' => $id]);
$possessionMT1 = $stmtTempsMT1->fetch(PDO::FETCH_ASSOC);

$pourcentage_beziers_mt1 = $pourcentage_adverse_mt1 = 0;

if (
    $possessionMT1 &&
    !empty($possessionMT1['possession_mt_1_total']) &&
    $possessionMT1['possession_mt_1_total'] !== '00:00:00'
) {
    $total_sec = convertToSeconds($possessionMT1['possession_mt_1_total']);
    $beziers_sec = convertToSeconds($possessionMT1['possession_mt_1_beziers']);
    $adverse_sec = convertToSeconds($possessionMT1['possession_mt_1_equipe_adverse']);

    if ($total_sec > 0) {
        $pourcentage_beziers_mt1 = round(($beziers_sec / $total_sec) * 100);
        $pourcentage_adverse_mt1 = round(($adverse_sec / $total_sec) * 100);
    }
}

$sqlTempsmt2 = "
select possession_mt_2.possession_mt_2_beziers, possession_mt_2.possession_mt_2_equipe_adverse, possession_mt_2.possession_mt_2_total
from possession_mt_2
join `match` m ON m.id_possession_mt_2=possession_mt_2.id_possession_mt_2 
where m.id_match = :id
";

$stmtTempsMT2 = $pdo->prepare($sqlTempsmt2);
$stmtTempsMT2->execute(['id' => $id]);
$possessionMT2 = $stmtTempsMT2->fetch(PDO::FETCH_ASSOC);

$pourcentage_beziers_mt2 = $pourcentage_adverse_mt2 = 0;

if (
    $possessionMT2 &&
    !empty($possessionMT2['possession_mt_2_total']) &&
    $possessionMT2['possession_mt_2_total'] !== '00:00:00'
) {
    $total_sec = convertToSeconds($possessionMT2['possession_mt_2_total']);
    $beziers_sec = convertToSeconds($possessionMT2['possession_mt_2_beziers']);
    $adverse_sec = convertToSeconds($possessionMT2['possession_mt_2_equipe_adverse']);

    if ($total_sec > 0) {
        $pourcentage_beziers_mt2 = round(($beziers_sec / $total_sec) * 100);
        $pourcentage_adverse_mt2 = round(($adverse_sec / $total_sec) * 100);
    }
}


// Récupération sécurisée des valeurs
$mi_temps = $_GET['mi_temps'] ?? 1;
$nom_equipe = $_GET['nom_equipe'] ?? null; // en réalité ici c'est nom_equipe
$id_match = $_GET['id'] ?? null;

if ($id_match && $nom_equipe) {
    $champ_mitemps = ($mi_temps == 1) ? 'mt1' : 'mt2';

    $sqlFinAction = "
    SELECT 
        fac.id_fin_actions_collectives, 
        fac.total, 
        fac.{$champ_mitemps} as mt, 
        fac.action,
        m.id_match, 
        m.locaux, 
        m.visiteurs,
        el.nom_equipe AS equipe_locale,
        ev.nom_equipe AS equipe_visiteur
    FROM fin_actions_collectives fac
    JOIN equipe eq ON fac.id_equipe = eq.id_equipe
    JOIN `match` m ON m.id_match = :id
    JOIN equipe el ON el.nom_equipe = m.locaux
    JOIN equipe ev ON ev.nom_equipe = m.visiteurs
    WHERE eq.nom_equipe = :nom_equipe
    ";

    $stmtFinAction = $pdo->prepare($sqlFinAction);
    $stmtFinAction->execute([
        'id' => $id_match,
        'nom_equipe' => $nom_equipe
    ]);

    $dataFinAction = $stmtFinAction->fetchAll(PDO::FETCH_ASSOC);
} else {
    echo "<p>Paramètres manquants : id du match ou nom de l'équipe.</p>";
}


?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Détail du match</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>
<body class="bg-gradient-to-b from-[#1f2355] to-black text-white min-h-screen py-10 font-sans">
<?php include 'sidebar.php'; ?>

<div class="bg-gray-900 text-white max-w-6xl mx-auto p-6 space-y-8 rounded-lg shadow-lg mb-8">
  <div class="text-center text-2xl font-bold">
    <span class="<?= $match['score_locaux'] > $match['score_visiteurs'] ? 'text-green-400' : ($match['score_locaux'] < $match['score_visiteurs'] ? 'text-red-400' : 'text-yellow-400') ?>">
      <?= h($match['locaux']) ?> <?= $match['score_locaux'] ?>
    </span>
    <span class="text-white"> – </span>
    <span class="<?= $match['score_visiteurs'] > $match['score_locaux'] ? 'text-green-400' : ($match['score_visiteurs'] < $match['score_locaux'] ? 'text-red-400' : 'text-yellow-400') ?>">
      <?= $match['score_visiteurs'] ?> <?= h($match['visiteurs']) ?>
    </span>
  </div>
  <div class="text-center text-sm text-gray-300 mt-2 space-y-1">
    <p><strong>Compétition :</strong> <?= h($match['competition']) ?></p>
    <p><strong>Journée :</strong> <?= h($match['journee']) ?></p>
    <p><strong>Date :</strong> <?= date('d/m/Y', strtotime($match['date'])) ?></p>
  </div>

</div>

<div class="bg-gray-900 text-white max-w-6xl mx-auto p-6 space-y-8 rounded-lg shadow-lg mb-8">

  <div class="flex flex-col lg:flex-row justify-center gap-12">
    
    <!-- Bloc Temps Effectif -->
    <?php if ($tempsEffectif): ?>
    <div class="flex flex-col items-center">
      <div class="text-center text-sm text-gray-300 mb-4">
        <p><strong>Temps effectif total :</strong> <?= h($tempsEffectif['temps_effectif_total']) ?> min</p>
      </div>
      <div class="flex justify-center items-center gap-8">
        <!-- Cercle Béziers -->
        <div class="relative w-24 h-24">
          <svg class="w-full h-full" viewBox="0 0 36 36">
            <path class="text-gray-700" stroke="currentColor" stroke-width="3.8" fill="none"
              d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
            <path class="text-blue-500" stroke="currentColor" stroke-width="3.8"
              stroke-dasharray="<?= $pourcentage_beziers ?>, 100" fill="none"
              d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
          </svg>
          <div class="absolute inset-0 flex flex-col items-center justify-center text-sm">
            <span><?= $pourcentage_beziers ?>%</span>
            <span class="text-xs text-blue-400 text-center"><?= $match['locaux'] ?></span>
          </div>
          <p><?= h($tempsEffectif['temps_effectif_beziers']) ?> min</p>
        </div>

        <!-- Cercle Adverse -->
        <div class="relative w-24 h-24">
          <svg class="w-full h-full" viewBox="0 0 36 36">
            <path class="text-gray-700" stroke="currentColor" stroke-width="3.8" fill="none"
              d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
            <path class="text-red-500" stroke="currentColor" stroke-width="3.8"
              stroke-dasharray="<?= $pourcentage_adverse ?>, 100" fill="none"
              d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
          </svg>
          <div class="absolute inset-0 flex flex-col items-center justify-center text-sm">
            <span><?= $pourcentage_adverse ?>%</span>
            <span class="text-xs text-red-400 text-center"><?= $match['visiteurs'] ?></span>
          </div>
          <p><?= h($tempsEffectif['temps_effectif_equipe_adverse']) ?> min</p>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Bloc Possession MT1 -->
    <?php if ($possessionMT1): ?>
    <div class="flex flex-col items-center">
      <div class="text-center text-sm text-gray-300 mb-4">
        <p><strong>Possession 1ère mi-temps :</strong> <?= h($possessionMT1['possession_mt_1_total']) ?> min</p>
      </div>

      <?php
        $beziers_pct = $pourcentage_beziers_mt1;
        $adverse_pct = 100 - $beziers_pct;
      ?>

      <div class="flex justify-center items-center">
        <div class="relative w-24 h-24">
          <svg class="w-full h-full transform -rotate-90" viewBox="0 0 36 36">
            <path class="text-gray-700" stroke="currentColor" stroke-width="3.8" fill="none"
              d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
            <path stroke="url(#grad)" stroke-width="3.8" fill="none" stroke-dasharray="100" stroke-dashoffset="0"
              d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
            <defs>
              <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="0%">
                <stop offset="0%" stop-color="#3B82F6" />
                <stop offset="<?= $beziers_pct ?>%" stop-color="#3B82F6" />
                <stop offset="<?= $beziers_pct ?>%" stop-color="#EF4444" />
                <stop offset="100%" stop-color="#EF4444" />
              </linearGradient>
            </defs>
          </svg>
          <div class="absolute inset-0 flex flex-col items-center justify-center text-xs text-center">
            <span class="text-blue-400"><?= h($match['locaux']) ?>: <?= $beziers_pct ?>%</span>
            <span class="text-red-400"><?= h($match['visiteurs']) ?>: <?= $adverse_pct ?>%</span>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Bloc Possession MT2 -->
    <?php if ($possessionMT2): ?>
    <div class="flex flex-col items-center">
      <div class="text-center text-sm text-gray-300 mb-4">
        <p><strong>Possession 2ème mi-temps :</strong> <?= h($possessionMT2['possession_mt_2_total']) ?> min</p>
      </div>

      <?php
        $beziers_pct = $pourcentage_beziers_mt2;
        $adverse_pct = 100 - $beziers_pct;
      ?>

      <div class="flex justify-center items-center">
        <div class="relative w-24 h-24">
          <svg class="w-full h-full transform -rotate-90" viewBox="0 0 36 36">
            <path class="text-gray-700" stroke="currentColor" stroke-width="3.8" fill="none"
              d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
            <path stroke="url(#grad)" stroke-width="3.8" fill="none" stroke-dasharray="100" stroke-dashoffset="0"
              d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
            <defs>
              <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="0%">
                <stop offset="0%" stop-color="#3B82F6" />
                <stop offset="<?= $beziers_pct ?>%" stop-color="#3B82F6" />
                <stop offset="<?= $beziers_pct ?>%" stop-color="#EF4444" />
                <stop offset="100%" stop-color="#EF4444" />
              </linearGradient>
            </defs>
          </svg>
          <div class="absolute inset-0 flex flex-col items-center justify-center text-xs text-center">
            <span class="text-blue-400"><?= h($match['locaux']) ?>: <?= $beziers_pct ?>%</span>
            <span class="text-red-400"><?= h($match['visiteurs']) ?>: <?= $adverse_pct ?>%</span>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div>

</div>

<div class="max-w-6xl mx-auto mt-10 mb-8 flex flex-col lg:flex-row gap-6">
  <!-- Bloc Locaux -->
  <div class="bg-gray-900 text-white p-6 rounded-lg shadow-lg flex-1">
    <div class="text-xl font-bold mb-4"><?= h($match['locaux']) ?> (Score : <?= h($match['score_locaux']) ?>)</div>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
      <div class="bg-gray-800 p-4 rounded-lg text-center">
        <div class="text-gray-400">Essais</div>
        <div class="text-lg font-semibold"><?= h($score_locaux['essais']) ?></div>
      </div>
      <div class="bg-gray-800 p-4 rounded-lg text-center">
        <div class="text-gray-400">Transformations</div>
        <div class="text-lg font-semibold"><?= h($score_locaux['transformations']) ?></div>
      </div>
      <div class="bg-gray-800 p-4 rounded-lg text-center">
        <div class="text-gray-400">Drops</div>
        <div class="text-lg font-semibold"><?= h($score_locaux['drops']) ?></div>
      </div>
      <div class="bg-gray-800 p-4 rounded-lg text-center">
        <div class="text-gray-400">Drops tentés</div>
        <div class="text-lg font-semibold"><?= h($score_locaux['drops_tentes']) ?></div>
      </div>
      <div class="bg-gray-800 p-4 rounded-lg text-center">
        <div class="text-gray-400">Pénalités</div>
        <div class="text-lg font-semibold"><?= h($score_locaux['penalites']) ?></div>
      </div>
      <div class="bg-gray-800 p-4 rounded-lg text-center">
        <div class="text-gray-400">Pénalités tentées</div>
        <div class="text-lg font-semibold"><?= h($score_locaux['penalites_tentees']) ?></div>
      </div>
    </div>
  </div>
<div class="max-w-4xl mx-auto bg-gray-900 text-white p-6 rounded-lg shadow-lg mb-10">
  <h2 class="text-xl font-semibold text-white mb-4">Comparaison des statistiques</h2>
  <canvas id="statsChart" height="200"></canvas>
</div>
  <!-- Bloc Visiteurs -->
  <div class="bg-gray-900 text-white p-6 rounded-lg shadow-lg flex-1">
    <div class="text-xl font-bold mb-4"><?= h($match['visiteurs']) ?> (Score : <?= h($match['score_visiteurs']) ?>)</div>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
      <div class="bg-gray-800 p-4 rounded-lg text-center">
        <div class="text-gray-400">Essais</div>
        <div class="text-lg font-semibold"><?= h($score_visiteurs['essais']) ?></div>
      </div>
      <div class="bg-gray-800 p-4 rounded-lg text-center">
        <div class="text-gray-400">Transformations</div>
        <div class="text-lg font-semibold"><?= h($score_visiteurs['transformations']) ?></div>
      </div>
      <div class="bg-gray-800 p-4 rounded-lg text-center">
        <div class="text-gray-400">Drops</div>
        <div class="text-lg font-semibold"><?= h($score_visiteurs['drops']) ?></div>
      </div>
      <div class="bg-gray-800 p-4 rounded-lg text-center">
        <div class="text-gray-400">Drops tentés</div>
        <div class="text-lg font-semibold"><?= h($score_visiteurs['drops_tentes']) ?></div>
      </div>
      <div class="bg-gray-800 p-4 rounded-lg text-center">
        <div class="text-gray-400">Pénalités</div>
        <div class="text-lg font-semibold"><?= h($score_visiteurs['penalites']) ?></div>
      </div>
      <div class="bg-gray-800 p-4 rounded-lg text-center">
        <div class="text-gray-400">Pénalités tentées</div>
        <div class="text-lg font-semibold"><?= h($score_visiteurs['penalites_tentees']) ?></div>
      </div>
    </div>
  </div>
  
</div>

<script>
const ctx = document.getElementById('statsChart').getContext('2d');

const statsChart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: ['Essais', 'Transformations', 'Drops', 'Drops tentés', 'Pénalités', 'Pénalités tentées'],
    datasets: [
      {
        label: '<?= h($match["locaux"]) ?>',
        data: [
          <?= h($score_locaux['essais']) ?>,
          <?= h($score_locaux['transformations']) ?>,
          <?= h($score_locaux['drops']) ?>,
          <?= h($score_locaux['drops_tentes']) ?>,
          <?= h($score_locaux['penalites']) ?>,
          <?= h($score_locaux['penalites_tentees']) ?>
        ],
        borderColor: '#3b82f6',
        backgroundColor: 'rgba(59, 130, 246, 0.7)',
        fill: true,
        tension: 0.3,
        pointRadius: 4
      },
      {
        label: '<?= h($match["visiteurs"]) ?>',
        data: [
          <?= h($score_visiteurs['essais']) ?>,
          <?= h($score_visiteurs['transformations']) ?>,
          <?= h($score_visiteurs['drops']) ?>,
          <?= h($score_visiteurs['drops_tentes']) ?>,
          <?= h($score_visiteurs['penalites']) ?>,
          <?= h($score_visiteurs['penalites_tentees']) ?>
        ],
        borderColor: '#ef4444',
        backgroundColor: 'rgba(239, 68, 68, 0.7)',
        fill: true,
        tension: 0.3,
        pointRadius: 4
      }
    ]
  },
  options: {
    responsive: true,
    plugins: {
      legend: {
        position: 'top'
      },
      tooltip: {
        mode: 'index',
        intersect: false
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        ticks: {
          precision: 0
        }
      }
    }
  }
});
</script>


<div class="bg-gray-900 text-white max-w-6xl mx-auto p-6 mt-10 rounded-lg shadow-lg mb-8">
  <h2 class="text-xl font-bold mb-6 text-center">POINTS / ACTION</h2>

  <div class="flex flex-col lg:flex-row gap-6">
    <!-- Tableau -->
    <div class="lg:w-1/2 w-full">
      <table class="w-full text-sm border-separate border-spacing-y-1">
        <thead class="text-gray-400 text-xs">
          <tr>
            <th class="text-left px-2">Action</th>
            <th colspan="4" class="text-center"><?= h($match['locaux']) ?></th>
            <th colspan="4" class="text-center"><?= h($match['visiteurs']) ?></th>
          </tr>
          <tr>
            <th></th>
            <th class="text-center">Total</th>
            <th class="text-center text-green-400">+</th>
            <th class="text-center text-yellow-400">~</th>
            <th class="text-center text-red-400">–</th>
            <th class="text-center">Total</th>
            <th class="text-center text-green-400">+</th>
            <th class="text-center text-yellow-400">~</th>
            <th class="text-center text-red-400">–</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-700">
          <?php foreach ($actionsData as $action => $equipes): ?>
            <tr>
              <td class="font-medium px-2"><?= h($action) ?></td>
              <?php foreach ([$match['locaux'], $match['visiteurs']] as $equipe): ?>
                <?php $data = $equipes[$equipe] ?? ['total' => 0, 'positifs' => 0, 'neutres' => 0, 'negatifs' => 0]; ?>
                <td class="text-center"><?= $data['total'] ?></td>
                <td class="text-center text-green-400"><?= $data['positifs'] ?></td>
                <td class="text-center text-yellow-400"><?= $data['neutres'] ?></td>
                <td class="text-center text-red-400"><?= $data['negatifs'] ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Graphe -->
    <div class="lg:w-1/2 w-full">
      <h3 class="text-center font-semibold text-base mb-2">Actions</h3>
      <div class="relative h-72">
        <canvas id="barChart"></canvas>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labels = <?= json_encode(array_keys($actionsData)) ?>;
const locaux = <?= json_encode(array_map(fn($a) => ($a[$match['locaux']]['total'] ?? 0), $actionsData)) ?>;
const visiteurs = <?= json_encode(array_map(fn($a) => ($a[$match['visiteurs']]['total'] ?? 0), $actionsData)) ?>;

new Chart(document.getElementById('barChart'), {
  type: 'bar',
  data: {
    labels: labels,
    datasets: [
      {
        label: '<?= $match['locaux'] ?>',
        data: locaux,
        backgroundColor: 'rgba(59, 130, 246, 0.7)', // bleu
      },
      {
        label: '<?= $match['visiteurs'] ?>',
        data: visiteurs,
        backgroundColor: 'rgba(239, 68, 68, 0.7)', // rouge
      }
    ]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { position: 'top' },
      title: { display: false }
    },
    scales: {
      x: {
        ticks: { color: '#ccc' },
        stacked: false
      },
      y: {
        beginAtZero: true,
        ticks: { color: '#ccc' },
        title: {
          display: true,
          text: 'Points',
          color: '#ccc'
        }
      }
    }
  }
});
</script>

<div class="bg-gray-900 text-white max-w-6xl mx-auto p-6 mt-10 rounded-lg shadow-lg mb-8">
 <h2>Filtrer les actions</h2>

  <form action="" method="get">
    <input type="hidden" name="id" value="<?= htmlspecialchars($match['id_match']) ?>">

    <label for="mi_temps">Choisir la mi-temps :</label>
    <select id="mi_temps" name="mi_temps" onchange="this.form.submit()">
        <option value="1" <?= ($mi_temps == 1) ? 'selected' : '' ?>>1ère Mi-temps</option>
        <option value="2" <?= ($mi_temps == 2) ? 'selected' : '' ?>>2ème Mi-temps</option>
    </select>

    <label for="nom_equipe">Choisir l'équipe :</label>
    <select id="nom_equipe" name="nom_equipe" onchange="this.form.submit()">
        <option value="<?= htmlspecialchars($match['locaux']) ?>" <?= ($nom_equipe == $match['locaux']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($match['locaux']) ?>
        </option>
        <option value="<?= htmlspecialchars($match['visiteurs']) ?>" <?= ($nom_equipe == $match['visiteurs']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($match['visiteurs']) ?>
        </option>
    </select>
</form>



<script>
function updateActions() {
    const miTemps = document.getElementById('mi_temps').value;
    const nomEquipe = document.getElementById('nom_equipe').value;
    const urlParams = new URLSearchParams(window.location.search);
    const idMatch = urlParams.get('id'); // on garde le match actuel

    if (idMatch && nomEquipe) {
        window.location.href = `match.php?id=${idMatch}&nom_equipe=${nomEquipe}&mi_temps=${miTemps}`;
    }
}
</script>

    </div>


</div>

<div class="text-center pt-6">
  <a href="matchs.php" class="inline-block px-5 py-2 bg-white/80 text-black font-semibold rounded-md hover:bg-white transition">
    ↩ Liste
  </a>
</div>
</body>

