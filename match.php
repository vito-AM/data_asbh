<?php
session_start();
require_once 'db.php';
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
    m.temps_effectif_total,
    m.possession_mt_1_total,
    m.possession_mt_2_total,
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

$sqlTemps = "SELECT te.temps_effectif_e, e.nom_equipe
             FROM temps_effectif te
             JOIN equipe e ON e.id_equipe = te.id_equipe
             WHERE te.id_match = :id";

$stmtTemps = $pdo->prepare($sqlTemps);
$stmtTemps->execute(['id' => $id]);
$tempsEffectifs = $stmtTemps->fetchAll(PDO::FETCH_ASSOC);

function convertToSeconds(string $time): int {
    list($hours, $minutes, $seconds) = explode(':', $time);
    return ($hours * 3600) + ($minutes * 60) + $seconds;
}

$pourcentages = [];

if (
    $tempsEffectifs &&
    !empty($match['temps_effectif_total']) &&
    $match['temps_effectif_total'] !== '00:00:00'
) {
    $total_sec = convertToSeconds($match['temps_effectif_total']);

    foreach ($tempsEffectifs as $row) {
        $nomEquipe = $row['nom_equipe'];
        $tempsSec = convertToSeconds($row['temps_effectif_e']);
        $pourcentages[$nomEquipe] = ($total_sec > 0)
            ? round(($tempsSec / $total_sec) * 100)
            : 0;
    }
}


$sqlPossession1 = "SELECT p.possession_mt_1_e AS possession, e.nom_equipe
                   FROM possession_mt_1 p
                   JOIN equipe e ON e.id_equipe = p.id_equipe
                   WHERE p.id_match = :id";

$stmtPossession1 = $pdo->prepare($sqlPossession1);
$stmtPossession1->execute(['id' => $id]);
$possessions1 = $stmtPossession1->fetchAll(PDO::FETCH_ASSOC);

// Fonction pour convertir HH:MM:SS en secondes
function convertToSeconds1(string $time): int {
    list($hours, $minutes, $seconds) = explode(':', $time);
    return ($hours * 3600) + ($minutes * 60) + $seconds;
}

$pourcentagesPossession1 = [];
$total1_sec = 0;

// Total en secondes
foreach ($possessions1 as $row) {
    $total1_sec += convertToSeconds1($row['possession']);
}

// Calcul des pourcentages
foreach ($possessions1 as $row) {
    $equipe = $row['nom_equipe'];
    $temps_sec = convertToSeconds1($row['possession']);
    $pourcentagesPossession1[$equipe] = ($total1_sec > 0)
        ? round(($temps_sec / $total1_sec) * 100)
        : 0;
}


$sqlPossession2 = "SELECT p.possession_mt_2_e AS possession, e.nom_equipe
                   FROM possession_mt_2 p
                   JOIN equipe e ON e.id_equipe = p.id_equipe
                   WHERE p.id_match = :id";

$stmtPossession2 = $pdo->prepare($sqlPossession2);
$stmtPossession2->execute(['id' => $id]);
$possessions2 = $stmtPossession2->fetchAll(PDO::FETCH_ASSOC);

// Fonction pour convertir HH:MM:SS en secondes
function convertToSeconds2(string $time): int {
    list($hours, $minutes, $seconds) = explode(':', $time);
    return ($hours * 3600) + ($minutes * 60) + $seconds;
}

$pourcentagesPossession2 = [];
$total2_sec = 0;

// Total en secondes
foreach ($possessions2 as $row) {
    $total2_sec += convertToSeconds2($row['possession']);
}

// Calcul des pourcentages
foreach ($possessions2 as $row) {
    $equipe = $row['nom_equipe'];
    $temps_sec = convertToSeconds2($row['possession']);
    $pourcentagesPossession2[$equipe] = ($total2_sec > 0)
        ? round(($temps_sec / $total2_sec) * 100)
        : 0;
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
    JOIN `match` m ON m.id_match = fac.id_match
    JOIN equipe el ON el.nom_equipe = m.locaux
    JOIN equipe ev ON ev.nom_equipe = m.visiteurs
    WHERE eq.nom_equipe = :nom_equipe
    AND fac.id_match = :id
    ";

    $stmtFinAction = $pdo->prepare($sqlFinAction);
    $stmtFinAction->execute([
        'id' => $id_match,
        'nom_equipe' => $nom_equipe
    ]);

    $dataFinAction = $stmtFinAction->fetchAll(PDO::FETCH_ASSOC);

// On construit $match à partir des données
if (!isset($match) && !empty($dataFinAction)) {
    $match = [
        'id_match' => $dataFinAction[0]['id_match'],
        'locaux' => $dataFinAction[0]['locaux'],
        'visiteurs' => $dataFinAction[0]['visiteurs']
    ];
}


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
  <?php if ($sqlTemps): ?>
  <div class="flex flex-col items-center">
    <div class="text-center text-sm text-gray-300 mb-4">
      <p><strong>Temps effectif total :</strong> <?= h($match['temps_effectif_total']) ?> min</p>
    </div>
    <div class="flex justify-center items-center gap-8">

      <!-- Cercle Béziers -->
      <div class="relative w-24 h-24">
        <svg class="w-full h-full" viewBox="0 0 36 36">
          <path class="text-gray-700" stroke="currentColor" stroke-width="3.8" fill="none"
            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
          <path class="text-blue-500" stroke="currentColor" stroke-width="3.8"
            stroke-dasharray="<?= $pourcentages[$match['locaux']] ?? 0 ?>, 100" fill="none"
            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
        </svg>
        <div class="absolute inset-0 flex flex-col items-center justify-center text-sm">
          <span><?= $pourcentages[$match['locaux']] ?? 0 ?>%</span>
          <span class="text-xs text-blue-400 text-center"><?= h($match['locaux']) ?></span>
        </div>
        <p>
          <?= h(array_column($tempsEffectifs, 'temps_effectif_e', 'nom_equipe')[$match['locaux']] ?? '00:00:00') ?>
          min
        </p>
      </div>

      <!-- Cercle Adverse -->
      <div class="relative w-24 h-24">
        <svg class="w-full h-full" viewBox="0 0 36 36">
          <path class="text-gray-700" stroke="currentColor" stroke-width="3.8" fill="none"
            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
          <path class="text-red-500" stroke="currentColor" stroke-width="3.8"
            stroke-dasharray="<?= $pourcentages[$match['visiteurs']] ?? 0 ?>, 100" fill="none"
            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
        </svg>
        <div class="absolute inset-0 flex flex-col items-center justify-center text-sm">
          <span><?= $pourcentages[$match['visiteurs']] ?? 0 ?>%</span>
          <span class="text-xs text-red-400 text-center"><?= h($match['visiteurs']) ?></span>
        </div>
        <p>
          <?= h(array_column($tempsEffectifs, 'temps_effectif_e', 'nom_equipe')[$match['visiteurs']] ?? '00:00:00') ?>
          min
        </p>
      </div>

    </div>
  </div>
  <?php endif; ?>



      <!-- Bloc Possession MT1 -->
      <?php if ($possessions1): ?>
  <div class="flex flex-col items-center">
    <div class="text-center text-sm text-gray-300 mb-4">
      <p><strong>Possession 1ère mi-temps :</strong></p>
    </div>

    <?php
      $beziers_pct = $pourcentagesPossession1[$match['locaux']] ?? 0;
      $adverse_pct = $pourcentagesPossession1[$match['visiteurs']] ?? (100 - $beziers_pct);
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
     <?php if ($possessions2): ?>
  <div class="flex flex-col items-center">
    <div class="text-center text-sm text-gray-300 mb-4">
      <p><strong>Possession 2ᵉ mi-temps :</strong></p>
    </div>

    <?php
      $beziers_pct = $pourcentagesPossession2[$match['locaux']] ?? 0;
      $adverse_pct = $pourcentagesPossession2[$match['visiteurs']] ?? (100 - $beziers_pct);
    ?>

    <div class="flex justify-center items-center">
      <div class="relative w-24 h-24">
        <svg class="w-full h-full transform -rotate-90" viewBox="0 0 36 36">
          <path class="text-gray-700" stroke="currentColor" stroke-width="3.8" fill="none"
            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
          <path stroke="url(#grad2)" stroke-width="3.8" fill="none" stroke-dasharray="100" stroke-dashoffset="0"
            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
          <defs>
            <linearGradient id="grad2" x1="0%" y1="0%" x2="100%" y2="0%">
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
    <input type="hidden" name="id" value="<?= htmlspecialchars($match['id_match'] ?? '') ?>">

    <label for="mi_temps">Choisir la mi-temps :</label>
    <select id="mi_temps" name="mi_temps" onchange="updateActions()" class="bg-gray-800 text-white border border-gray-600 rounded px-3 py-2">
        <option value="1" <?= (isset($mi_temps) && $mi_temps == 1) ? 'selected' : '' ?>>1ère Mi-temps</option>
        <option value="2" <?= (isset($mi_temps) && $mi_temps == 2) ? 'selected' : '' ?>>2ème Mi-temps</option>
    </select>

    <label for="nom_equipe">Choisir l'équipe :</label>
    <select id="nom_equipe" name="nom_equipe" onchange="updateActions()" class="bg-gray-800 text-white border border-gray-600 rounded px-3 py-2">
        <option value="<?= htmlspecialchars($match['locaux'] ?? '') ?>" <?= (isset($nom_equipe) && $nom_equipe == ($match['locaux'] ?? '')) ? 'selected' : '' ?>>
            <?= htmlspecialchars($match['locaux'] ?? '') ?>
        </option>
        <option value="<?= htmlspecialchars($match['visiteurs'] ?? '') ?>" <?= (isset($nom_equipe) && $nom_equipe == ($match['visiteurs'] ?? '')) ? 'selected' : '' ?>>
            <?= htmlspecialchars($match['visiteurs'] ?? '') ?>
        </option>
    </select>
  </form>

  <script>
  function updateActions() {
      const miTemps = document.getElementById('mi_temps').value;
      const nomEquipe = document.getElementById('nom_equipe').value;
      const urlParams = new URLSearchParams(window.location.search);
      const idMatch = urlParams.get('id');

      if (idMatch && nomEquipe) {
          window.location.href = `match.php?id=${idMatch}&nom_equipe=${nomEquipe}&mi_temps=${miTemps}`;
      }
  }
  </script>

  <!-- INSERT ICI : le tableau filtré et repliable -->
  <?php
  $categoriesPrincipales = ['Mêlée', 'Ruck', 'Faute règlement', 'Touche', 'Maul', 'Coup d\'envoi', 'Plaquage', 'CPP', 'CPF'];
  $groupedActions = [];
  foreach ($dataFinAction as $action) {
      foreach ($categoriesPrincipales as $cat) {
          if (stripos($action['action'], $cat) === 0) {
              $groupedActions[$cat][] = $action;
              break;
          }
      }
  }
  ?>

  <?php if (!empty($groupedActions)): ?>
    <div class="mt-6 overflow-x-auto">
      <h3 class="text-lg font-semibold mb-4">Statistiques des actions (par catégorie) :</h3>
      <table class="min-w-full table-auto bg-gray-800 text-white rounded-lg overflow-hidden shadow-lg">
        <thead class="bg-gray-700 text-sm uppercase text-gray-300">
          <tr>
            <th class="px-4 py-3 text-left">Catégorie</th>
            <th class="px-4 py-3 text-center">1ère Mi-temps</th>
            <th class="px-4 py-3 text-center">2ème Mi-temps</th>
            <th class="px-4 py-3 text-center">Total</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-600">

          <?php foreach ($groupedActions as $cat => $actions): ?>
            <?php
              $totalMT1 = 0;
              $totalMT2 = 0;
              $totalGlobal = 0;
              foreach ($actions as $a) {
                  if (isset($mi_temps) && $mi_temps == 1) $totalMT1 += $a['mt'];
                  if (isset($mi_temps) && $mi_temps == 2) $totalMT2 += $a['mt'];
                  $totalGlobal += $a['total'];
              }
            ?>
            <tr class="hover:bg-gray-700 cursor-pointer transition" onclick="toggleDetails('<?= md5($cat) ?>')">
              <td class="px-4 py-2 font-semibold"><?= htmlspecialchars($cat) ?></td>
              <td class="px-4 py-2 text-center"><?= $totalMT1 ?: '-' ?></td>
              <td class="px-4 py-2 text-center"><?= $totalMT2 ?: '-' ?></td>
              <td class="px-4 py-2 text-center"><?= $totalGlobal ?></td>
            </tr>

            <tr id="details-<?= md5($cat) ?>" class="hidden bg-gray-700">
              <td colspan="4" class="px-4 py-2">
                <ul class="list-disc ml-5 text-sm space-y-1">
                  <?php foreach ($actions as $detail): ?>
                    <li>
                      <strong><?= htmlspecialchars($detail['action']) ?>:</strong>
                      MT: <?= htmlspecialchars($detail['mt']) ?> |
                      Total: <?= htmlspecialchars($detail['total']) ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </td>
            </tr>
          <?php endforeach; ?>

        </tbody>
      </table>
    </div>

    <script>
    function toggleDetails(id) {
      const row = document.getElementById('details-' + id);
      if (row) {
        row.classList.toggle('hidden');
      }
    }
    </script>

  <?php else: ?>
    <p class="mt-6 text-gray-400 italic">Aucune action trouvée pour ce filtre.</p>
  <?php endif; ?>
</div>




<?php if (isset($_GET['id']) && is_numeric($_GET['id'])): ?>
  <div class="text-center mt-4">
    <a href="match_pdf.php?id=<?= htmlspecialchars($_GET['id']) ?>" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
      Télécharger en PDF
    </a>
  </div>
<?php endif; ?>



<div class="text-center pt-6">
  <a href="matchs.php" class="inline-block px-5 py-2 bg-white/80 text-black font-semibold rounded-md hover:bg-white transition">
    ↩ Liste
  </a>
</div>
</body>

