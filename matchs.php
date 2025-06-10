<?php

/* matchs.php – liste des matchs ASBH (à partir des tables dim_team / fact_team_match) */

session_start();
require_once 'db.php';
require_once 'auth.php';
$pdo = getBD();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
ini_set('display_errors',1);

/* id de l'ASBH dans dim_team (code = 'ASBH') -------------------------------- */
$teamRow = $pdo->query("SELECT id_equipe FROM equipe WHERE nom_equipe = 'ASBH' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$ASBH_ID = $teamRow ? (int)$teamRow['id_equipe'] : 1;   // fallback 1

/* Récupération des matchs (jointure dim_team pour noms) ---------------------- */
$sql = "SELECT id_match, date AS date_match, competition, journee, locaux, visiteurs, score_locaux, score_visiteurs FROM `match` ORDER BY date DESC; ";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ASBH – Matchs</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config={theme:{extend:{colors:{primary:'#292E68',primaryDark:'#1f2355',danger:'#A00E0F'},fontFamily:{sans:['Inter'],'title':['\"Bebas Neue\"']}}}}
</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Bebas+Neue&display=swap" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-[#292E68] via-[#1f2355] to-black text-white font-sans min-h-screen">
<?php include 'sidebar.php'; ?>

<header class="ml-0 md:ml-48 sticky top-0 z-10 w-[calc(100%-12rem)] flex items-center justify-between px-4 py-3 bg-black/40 backdrop-blur flex-wrap gap-4">
  <div class="flex flex-wrap items-center gap-3 text-sm">
    <label>Domicile/Ext :
      <select id="domExt" class="text-black rounded p-1">
        <option value="all">Tous</option>
        <option value="dom">Domicile</option>
        <option value="ext">Extérieur</option>
      </select>
    </label>
    <label>Résultat :
      <select id="resFilter" class="text-black rounded p-1">
        <option value="all">Tous</option>
        <option value="win">Victoire</option>
        <option value="loss">Défaite</option>
        <option value="draw">Nul</option>
      </select>
    </label>
    <button onclick="resetFilters()" class="bg-white/20 hover:bg-white/30 px-3 py-1 rounded">Réinitialiser</button>
  </div>
</header>

<main class="ml-0 md:ml-48 p-6 space-y-4 max-w-5xl mx-auto">
<?php foreach($rows as $m):
      $dom = ($m['locaux']==='ASBH');
      $s1  = (int)$m['score_locaux'];
      $s2  = (int)$m['score_visiteurs'];
      $vic = ($dom && $s1>$s2)||(!$dom&&$s2>$s1);
      $nul = ($s1==$s2);
      $badge = $nul? 'orange-500':($vic? 'green-600':'red-600');
      $res   = $nul?'draw':($vic?'win':'loss');
?>
 <a href="match.php?id=<?= $m['id_match'] ?>" class="block">
<article data-dom="<?= $dom?'dom':'ext' ?>" data-res="<?= $res ?>" class="relative cursor-pointer hover:bg-white/10 transition border border-white/10 rounded-xl p-4 flex flex-col sm:flex-row items-center gap-4 flex-1 ml-64">

    <span class="absolute left-0 top-0 h-full w-1 rounded-l-xl bg-<?= $badge ?>"></span>
    <div class="flex-1 text-center">
      <p class="uppercase text-xs text-gray-300 mb-1"><?= htmlspecialchars($m['competition']) ?></p>
      <h3 class="font-semibold">Journée <?= htmlspecialchars($m['journee']) ?> – <?= date('d/m/Y', strtotime($m['date_match'])) ?></h3>
    </div>
    <div class="text-center font-title text-xl min-w-[140px]">
      <?php if($dom): ?>
        <b>ASBH</b> <?= $s1 ?> – <?= $s2 ?> <?= htmlspecialchars($m['visiteurs']) ?>
      <?php else: ?>
        <?= htmlspecialchars($m['locaux']) ?> <?= $s1 ?> – <?= $s2 ?> <b>ASBH</b>
      <?php endif; ?>
    </div>
  </article></a>
<?php endforeach; ?>
</main>

<script>
const cards=[...document.querySelectorAll('article[data-dom]')];
const domSel=document.getElementById('domExt');
const resSel=document.getElementById('resFilter');
function resetFilters(){domSel.value='all';resSel.value='all';filter();}
[domSel,resSel].forEach(el=>el.addEventListener('input',filter));
function filter(){
  const d=domSel.value,r=resSel.value;
  cards.forEach(c=>{
    const okDom=(d==='all'||c.dataset.dom===d);
    const okRes=(r==='all'||c.dataset.res===r);
    c.style.display=(okDom&&okRes)?'flex':'none';
  });
}
filter();
</script>
</body>
</html>

