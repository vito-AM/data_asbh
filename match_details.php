<?php
require_once 'db.php';
$pdo = getBD();

// Récupération des paramètres
$player = intval($_GET['player']  ?? 0);
$match  = intval($_GET['match']   ?? 0);

// 1) Récupérer le détail du match + IDP + détails JSON
$sql = "
  SELECT i.poste, i.minutes_jouees, i.idp, i.details,
         m.date, m.competition, m.locaux, m.visiteurs,
         m.score_locaux, m.score_visiteurs
  FROM   idp    AS i
  JOIN   `match` AS m ON m.id_match = i.id_match
  WHERE  i.id_joueur = ? AND i.id_match = ?
  LIMIT  1
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$player, $match]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
  echo '<p class="text-gray-400">Aucune donnée</p>';
  exit;
}

// Décoder les scores par catégorie (attaque, defense, spec, engagement, discipline, initiative)
$catDetails = json_decode($row['details'] ?? '[]', true);

// 2) Récupérer le rapport GPS
$gpsStmt = $pdo->prepare("
  SELECT periode, temps_de_jeu, distance_totale, `min`, marche, intensite, vmax, nb_accel
  FROM courir
  WHERE id_joueur = ? AND id_match = ?
  ORDER BY 
    CASE periode 
      WHEN 'Match entier' THEN 1
      WHEN 'Mi-temps 1'    THEN 2
      WHEN 'Mi-temps 2'    THEN 3
      ELSE 4
    END
");
$gpsStmt->execute([$player, $match]);
$gpsRows = $gpsStmt->fetchAll(PDO::FETCH_ASSOC);

// 3) Récupérer les actions détaillées
$actsStmt = $pdo->prepare("
  SELECT action, valeur
  FROM export_stat_match
  WHERE id_joueur = ? AND id_match = ?
  ORDER BY action
");
$actsStmt->execute([$player, $match]);
$actionRows = $actsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="bg-black/60 border border-white/10 rounded-xl p-6 space-y-6">

  <!-- HEADER : titre + score -->
  <header class="flex flex-col md:flex-row md:justify-between md:items-center">
    <h3 class="text-2xl font-bold text-white">
      <?= htmlspecialchars($row['competition']) ?>
      <span class="text-base text-gray-400">— <?= date('d M Y', strtotime($row['date'])) ?></span>
    </h3>
    <div class="mt-2 md:mt-0 text-white text-xl font-semibold">
      <?= htmlspecialchars($row['locaux']) ?>
      <span class="text-emerald-300"><?= $row['score_locaux'] ?></span>
      –
      <span class="text-red-400"><?= $row['score_visiteurs'] ?></span>
      <?= htmlspecialchars($row['visiteurs']) ?>
    </div>
  </header>

  <!-- INFOS JOUEUR : poste, temps, IDP -->
  <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm text-gray-300">
    <div>
      <dt class="font-medium">Poste</dt>
      <dd><?= htmlspecialchars($row['poste']) ?></dd>
    </div>
    <div>
      <dt class="font-medium">Temps de jeu</dt>
      <dd><?= (int)$row['minutes_jouees'] ?>’</dd>
    </div>
    <div>
      <dt class="font-medium">IDP</dt>
      <dd><?= round($row['idp']) ?></dd>
    </div>
  </dl>

  <!-- RAPPORT GPS -->
  <?php if (count($gpsRows) > 0): ?>
    <h4 class="text-white text-sm font-semibold">Rapport GPS</h4>
    <div class="overflow-x-auto">
      <table class="w-full text-gray-200 text-sm border-collapse">
        <thead>
          <tr class="bg-gray-800 text-left">
            <th class="py-2 px-3">Période</th>
            <th class="py-2 px-3 text-right">Tps Jeu (min)</th>
            <th class="py-2 px-3 text-right">Distance (m)</th>
            <th class="py-2 px-3 text-right">V Moy (m/min)</th>
            <th class="py-2 px-3 text-right">% Marche</th>
            <th class="py-2 px-3 text-right">% Intensité</th>
            <th class="py-2 px-3 text-right">V<sub>max</sub> (km/h)</th>
            <th class="py-2 px-3 text-right"># Accél.</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($gpsRows as $g): ?>
            <tr class="odd:bg-white/5">
              <td class="py-1 px-3"><?= htmlspecialchars($g['periode']) ?></td>
              <td class="py-1 px-3 text-right"><?= (int)$g['temps_de_jeu'] ?></td>
              <td class="py-1 px-3 text-right"><?= number_format($g['distance_totale'], 0, ',', ' ') ?></td>
              <td class="py-1 px-3 text-right"><?= number_format($g['min'], 1) ?></td>
              <td class="py-1 px-3 text-right"><?= number_format($g['marche'], 1) ?>%</td>
              <td class="py-1 px-3 text-right"><?= number_format($g['intensite'], 1) ?>%</td>
              <td class="py-1 px-3 text-right"><?= number_format($g['vmax'], 1) ?></td>
              <td class="py-1 px-3 text-right"><?= (int)$g['nb_accel'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="text-gray-400">Aucun rapport GPS disponible pour ce match.</p>
  <?php endif; ?>

  <!-- TABLEAU DÉTAIL PAR CATÉGORIE -->
  <?php if (!empty($catDetails)): ?>
    <h4 class="text-white text-sm font-semibold">Détail par catégorie</h4>
    <table class="w-full text-gray-200 text-sm border-collapse">
      <thead>
        <tr class="border-b border-gray-700">
          <th class="py-2 text-left">Catégorie</th>
          <th class="py-2 text-right">Score</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (['attaque','defense','spec','engagement','discipline','initiative'] as $cat): ?>
          <tr class="odd:bg-white/5">
            <td class="py-1"><?= ucfirst($cat) ?></td>
            <td class="py-1 text-right"><?= isset($catDetails[$cat]) ? $catDetails[$cat] : 0 ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  

  <!-- LISTE DES ACTIONS DÉTAILLÉES -->
  <?php if (count($actionRows) > 0): ?>
    <h4 class="text-white text-sm font-semibold mt-4">Actions détaillées</h4>
    <table class="w-full text-gray-200 text-xs border-collapse">
      <thead>
        <tr class="border-b border-gray-700">
          <th class="py-1 text-left">Action</th>
          <th class="py-1 text-right">Valeur</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($actionRows as $a): ?>
          <tr class="odd:bg-white/5">
            <td class="py-1"><?= htmlspecialchars($a['action']) ?></td>
            <td class="py-1 text-right"><?= (int)$a['valeur'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

</div>
