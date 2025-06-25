<?php
/* matchs.php – liste des matchs ASBH (tables `equipe` et `match`) */

session_start();
require_once 'db.php';
require_once 'auth.php';

$pdo = getBD();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
ini_set('display_errors', 1);

/* ──────────────── Récupération des matchs ──────────────── */
$sql = "
  SELECT id_match,
         date         AS date_match,
         competition,
         journee,
         locaux,
         visiteurs,
         score_locaux,
         score_visiteurs
  FROM `match`
  ORDER BY date DESC
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>DAT'ASBH – Matchs</title>
  <link rel="icon" href="images/logo_asbh.png" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary      : '#292E68',
            primaryDark  : '#1f2355',
            danger       : '#A00E0F',
          },
          fontFamily: {
            sans  : ['Inter', 'sans-serif'],
            title : ['"Bebas Neue"', 'cursive'],
          }
        }
      }
    }
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Bebas+Neue&display=swap" rel="stylesheet">
</head>

<body class="bg-gradient-to-br from-primary via-primaryDark to-black text-white font-sans min-h-screen">
<?php include 'sidebar.php'; ?>

<!-- ──────────────── BARRE FILTRES ──────────────── -->
<header class="sticky top-0 z-10 w-full md:pl-48 flex items-center gap-3 px-4 py-3 bg-black/40 backdrop-blur">
  <label>Domicile/Ext&nbsp;:
    <select id="domExt" class="text-black rounded p-1">
      <option value="all">Tous</option>
      <option value="dom">Domicile</option>
      <option value="ext">Extérieur</option>
    </select>
  </label>
  <label>Résultat&nbsp;:
    <select id="resFilter" class="text-black rounded p-1">
      <option value="all">Tous</option>
      <option value="win">Victoire</option>
      <option value="loss">Défaite</option>
      <option value="draw">Nul</option>
    </select>
  </label>
  <button onclick="resetFilters()" class="bg-white/20 hover:bg-white/30 px-3 py-1 rounded">
    Réinitialiser
  </button>
</header>

<!-- ──────────────── LISTE DES MATCHS ──────────────── -->
<main class="md:pl-48 p-6 flex flex-col gap-6 items-center">
<?php foreach ($rows as $m):
      $dom     = ($m['locaux'] === 'ASBH');
      $s1      = (int)$m['score_locaux'];
      $s2      = (int)$m['score_visiteurs'];
      $victoire= ($dom && $s1 > $s2) || (!$dom && $s2 > $s1);
      $nul     = ($s1 === $s2);

      $badgeColor = $nul ? 'orange-500' : ($victoire ? 'green-600' : 'red-600');
      $resultKey  = $nul ? 'draw'       : ($victoire ? 'win'       : 'loss');
?>
  <a href="match.php?id=<?= $m['id_match'] ?>"
     class="matchCard block max-w-xl w-full mx-auto"
     data-dom="<?= $dom ? 'dom' : 'ext' ?>"
     data-res="<?= $resultKey ?>">
    <article class="relative w-full border border-white/10 rounded-xl p-6 bg-white/5 hover:bg-white/10 transition">

      <!-- Barre latérale résultat -->
      <span class="absolute left-0 top-0 h-full w-1 rounded-l-xl bg-<?= $badgeColor ?>"></span>

      <!-- Compétition + date -->
      <p class="uppercase text-[0.65rem] tracking-wide text-gray-300 text-center mb-2">
        <?= htmlspecialchars($m['competition']) ?> – <?= htmlspecialchars($m['journee']) ?><br>
        <?= date('d/m/Y', strtotime($m['date_match'])) ?>
      </p>

      <!-- Score -->
      <div class="flex items-center justify-center gap-2 font-title text-xl md:text-2xl">
        <?php if ($dom): ?>
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

<!-- ──────────────── JS FILTRES ──────────────── -->
<script>
const cards  = [...document.querySelectorAll('.matchCard')];
const domSel = document.getElementById('domExt');
const resSel = document.getElementById('resFilter');

domSel.addEventListener('change', filter);
resSel.addEventListener('change', filter);

function resetFilters() {
  domSel.value = 'all';
  resSel.value = 'all';
  filter();
}

function filter() {
  const d = domSel.value;   // 'all' | 'dom' | 'ext'
  const r = resSel.value;   // 'all' | 'win' | 'loss' | 'draw'

  cards.forEach(card => {
    const okDom = (d === 'all' || card.dataset.dom === d);
    const okRes = (r === 'all' || card.dataset.res === r);
    card.style.display = (okDom && okRes) ? '' : 'none';
  });
}

filter();   // affichage initial
</script>

<script src="js/chat-widget.js" defer></script>
</body>
</html>
