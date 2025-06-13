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

<header class="sticky top-0 z-10 w-full md:ml-48 flex items-center justify-between px-4 py-3 bg-black/40 backdrop-blur flex-wrap gap-4">
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

<!-- Container principal centré horizontalement -->
<main class="md:ml-48 p-6 flex flex-col gap-6 items-center">
<?php foreach($rows as $m):
      $dom = ($m['locaux']==='ASBH');
      $s1  = (int)$m['score_locaux'];
      $s2  = (int)$m['score_visiteurs'];
      $vic = ($dom && $s1>$s2)||(!$dom&&$s2>$s1);
      $nul = ($s1==$s2);
      $badge = $nul? 'orange-500':($vic? 'green-600':'red-600');
      $res   = $nul?'draw':($vic?'win':'loss');
?>
  <!-- Chaque carte est limitée en largeur et centrée par mx-auto -->
  <a href="match.php?id=<?= $m['id_match'] ?>" class="block max-w-xl w-full mx-auto">
    <article data-dom="<?= $dom?'dom':'ext' ?>" data-res="<?= $res ?>" class="relative w-full cursor-pointer border border-white/10 rounded-xl p-6 bg-white/5 hover:bg-white/10 transition">
      <!-- Barre latérale couleur résultat -->
      <span class="absolute left-0 top-0 h-full w-1 rounded-l-xl bg-<?= $badge ?>"></span>

      <!-- Infos compétition/journée/date -->
      <p class="uppercase text-[0.65rem] tracking-wide text-gray-300 text-center mb-2">
        <?= htmlspecialchars($m['competition']) ?> – <?= htmlspecialchars($m['journee']) ?> </br> <?= date('d/m/Y', strtotime($m['date_match'])) ?>
      </p>

      <!-- Ligne score -->
      <div class="flex items-center justify-center gap-2 font-title text-xl md:text-2xl">
        <?php if($dom): ?>
          <span class="font-bold">ASBH</span>
          <span class="font-semibold"><?= $s1 ?></span>
          <span>-</span>
          <span class="font-semibold"><?= $s2 ?></span>
          <span><?= htmlspecialchars($m['visiteurs']) ?></span>
        <?php else: ?>
          <span><?= htmlspecialchars($m['locaux']) ?></span>
          <span class="font-semibold"><?= $s1 ?></span>
          <span>-</span>
          <span class="font-semibold"><?= $s2 ?></span>
          <span class="font-bold">ASBH</span>
        <?php endif; ?>
      </div>
    </article>
  </a>
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
    c.style.display=(okDom&&okRes)?'block':'none';
  });
}
filter();
</script>
<script src="js/chat-widget.js" defer></script>
</body>
</html>
