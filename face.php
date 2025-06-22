<?php
/* compare_joueurs.php — Comparateur de joueurs ASBH (v2.2)
   ---------------------------------------------------------------------------
   Modifications v2.2 :
      • Couleur du texte des noms de joueurs en blanc dans la liste déroulante
      • Affichage du poste sous le nom du joueur en blanc (cartes)
      • Bouton « Réinitialiser » maintenu sous « Mettre à jour » avec une flèche indicative
*/

require_once 'db.php';
require_once 'auth.php';
$pdo = getBD();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ---------------------------------------------------------------------------
   Helpers                                                                    */
function fetchStats(PDO $pdo, int $idJoueur): array {
  // IDP : match / minutes / idp moyen   --------------------------------------
  $r = $pdo->prepare("SELECT COUNT(DISTINCT id_match) AS matches,
                             SUM(minutes_jouees)      AS minutes,
                             AVG(idp)                 AS idp_avg
                      FROM   idp
                      WHERE  id_joueur = ?");
  $r->execute([$idJoueur]);
  $idp = $r->fetch(PDO::FETCH_ASSOC) ?: [];

  // Points / essais -----------------------------------------------------------
  $p = $pdo->prepare("SELECT SUM(CASE WHEN action='Total Points' THEN valeur END) AS points,
                             SUM(CASE WHEN action='Points - Essai' THEN valeur END) AS essais
                      FROM   export_stat_match
                      WHERE  id_joueur = ?");
  $p->execute([$idJoueur]);
  $pts = $p->fetch(PDO::FETCH_ASSOC) ?: [];

  // Distance totale -----------------------------------------------------------
  $d = $pdo->prepare("SELECT SUM(distance_totale) FROM courir WHERE id_joueur = ?");
  $d->execute([$idJoueur]);
  $dist = $d->fetchColumn();

  return [
    'matches'  => (int)($idp['matches']  ?? 0),
    'minutes'  => (int)($idp['minutes']  ?? 0),
    'idp_avg'  => $idp['idp_avg'] !== null ? round($idp['idp_avg'],1) : 'N/A',
    'points'   => (int)($pts['points']   ?? 0),
    'essais'   => (int)($pts['essais']   ?? 0),
    'distance' => $dist ? round($dist/1000,1) : 0,
  ];
}

/* ---------------------------------------------------------------------------
   Liste des joueurs                                                          */
$joueurs = $pdo->query("SELECT id_joueur, nom_joueur, prenom_joueur, photo_path, poste, poste_secondaire, date_naissance, taille_cm, poids_kg
                        FROM joueur WHERE activite='actif' ORDER BY nom_joueur, prenom_joueur")
               ->fetchAll(PDO::FETCH_ASSOC);

/* ---------------------------------------------------------------------------
   Sélections                                                                  */
$nb_joueurs = isset($_GET['nb_joueurs']) ? max(2, min((int)$_GET['nb_joueurs'], 10)) : 2;
$joueurs_selectionnes = [];
for ($i=1; $i<=$nb_joueurs; $i++) {
  $joueur = null;
  // Si id transmis (cas anonymisé)
  if (!empty($_GET["joueurId$i"])) {
    $stmt = $pdo->prepare("SELECT * FROM joueur WHERE id_joueur = ? LIMIT 1");
    $stmt->execute([(int)$_GET["joueurId$i"]]);
    $joueur = $stmt->fetch(PDO::FETCH_ASSOC);
  }
  // Sinon par nom complet
  elseif (!empty($_GET["joueur$i"])) {
    $stmt = $pdo->prepare("SELECT * FROM joueur WHERE CONCAT(prenom_joueur,' ',nom_joueur)=? LIMIT 1");
    $stmt->execute([$_GET["joueur$i"]]);
    $joueur = $stmt->fetch(PDO::FETCH_ASSOC);
  }
  if ($joueur) {
    $joueur['stats']   = fetchStats($pdo, (int)$joueur['id_joueur']);
    $joueur['anonyme'] = isset($_GET["anonymize$i"]);
    $joueurs_selectionnes[] = $joueur;
  }
}
// Doublons -------------------------------------------------------------------
$ids = array_column($joueurs_selectionnes,'id_joueur');
if (count(array_unique($ids)) < count($ids)) {
  $errDoublon = true;
  $joueurs_selectionnes = [];
} else {
  $errDoublon = false;
}

?><!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>DAT'ASBH - Comparer les joueurs</title>
  <link rel="icon" type="image/png" href="images/logo_asbh.png" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Bebas+Neue&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: { primary: '#A00E0F', primaryDark: '#5e0000' },
          fontFamily: { sans: ['Inter'], title: ['"Bebas Neue"'] }
        }
      }
    };
  </script>
</head>
<body class="bg-gradient-to-br from-primary to-black text-white min-h-screen flex">
<?php include 'sidebar.php'; ?>

<div class="flex-1 ml-64 flex flex-col">
  <main class="flex-1 w-full max-w-7xl mx-auto p-6 md:p-10">
    <header class="flex flex-col items-center space-y-3 mb-10">
  <h1 class="text-3xl md:text-4xl font-bold">Comparer les joueurs</h1>

  <p class="text-sm text-white/80 text-center max-w-xl">
    Choisissez entre <strong>2&nbsp;et&nbsp;10</strong> joueurs actifs dans le menu déroulant,
    puis cliquez sur </br><em>« Valider »</em>.  
    Vous pouvez masquer l’identité d’un joueur avec le bouton
  </br><em>« Anonymiser »</em> ou revenir aux sélections par défaut grâce
    au bouton <em>« Réinitialiser »</em>.
  </p>
</header>
    <!-- Formulaire -->
    <form method="get" class="bg-white/5 backdrop-blur-md rounded-xl shadow-lg p-6 md:p-8 mb-12">
      <div class="flex flex-col md:flex-row md:items-end gap-6">
        <div class="grow">
          <label for="nb_joueurs" class="block text-sm font-semibold mb-2">Nombre de joueurs (2-10)</label>
          <input type="number" name="nb_joueurs" id="nb_joueurs" min="2" max="10" value="<?= $nb_joueurs ?>"
                 class="w-24 text-center bg-white/20 border border-white/30 rounded-xl py-1 focus:outline-none">
        </div>
        <!-- Groupe de boutons -->
  <div class="flex gap-3">
    <button type="submit"
            class="bg-primary hover:bg-primaryDark px-6 py-2 rounded-xl font-semibold transition">
      Valider
    </button>

    <button type="button" onclick="window.location='face.php'"
            class="bg-white/20 hover:bg-white/30 px-6 py-2 rounded-xl text-sm font-semibold flex items-center gap-2">
      <span class="text-lg">&larr;</span> Réinitialiser
    </button>
  </div>
</div>
      <div id="joueur-selects" class="mt-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"></div>
    </form>

    <?php if ($errDoublon): ?>
      <p class="text-center text-red-400 font-semibold mb-6">Veuillez sélectionner des joueurs différents.</p>
    <?php endif; ?>

    <?php if (!empty($joueurs_selectionnes)): ?>
      <div class="grid gap-8" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
        <?php foreach ($joueurs_selectionnes as $j): ?>
          <?php $href = $j['anonyme'] ? '#' : 'joueur.php?id=' . $j['id_joueur']; ?>
          <a href="<?= $href ?>" class="group <?= $j['anonyme'] ? 'pointer-events-none' : '' ?>">
            <div class="relative bg-white/10 rounded-2xl p-6 flex flex-col h-full shadow-lg overflow-hidden transition-transform group-hover:scale-105">
              <!-- En-tête -->
              <div class="flex items-center gap-4 pb-4 mb-4 border-b border-white/10">
                <img src="<?= $j['anonyme'] ? 'images/anonyme.jpg' : htmlspecialchars($j['photo_path'] ?: 'images/anonyme.jpg') ?>"
                     alt="Photo joueur" class="w-20 h-20 rounded-full object-cover border-2 border-primary" />
                <div>
                  <h2 class="text-xl font-bold leading-tight">
                    <?= $j['anonyme'] ? 'Joueur Anonyme' : htmlspecialchars($j['prenom_joueur'] . ' ' . $j['nom_joueur']) ?>
                  </h2>
                  <?php if (!$j['anonyme']): ?>
  <p class="text-white text-sm font-medium"><?= htmlspecialchars($j['poste']) ?></p>
<?php endif; ?>

                </div>
              </div>
              <!-- Bio -->
              <?php if (!$j['anonyme']): ?>
  <ul class="text-sm grid grid-cols-3 gap-24 mb-4">
    <li><span class="text-gray-300">Âge&nbsp;:</span> <span class="font-semibold"><?= date_diff(date_create($j['date_naissance']), date_create('today'))->y ?> ans</span></li>
    <li><span class="text-gray-300">Taille&nbsp;:</span> <span class="font-semibold"><?= htmlspecialchars($j['taille_cm']) ?> cm</span></li>
    <li><span class="text-gray-300">Poids&nbsp;:</span> <span class="font-semibold"><?= htmlspecialchars($j['poids_kg']) ?> kg</span></li>
  </ul>
<?php endif; ?>

              <!-- Stats -->
              <div class="mt-auto bg-black/20 rounded-lg p-4">
                <h3 class="text-center text-xs font-semibold uppercase tracking-wide text-gray-300 mb-3">Statistiques globales</h3>
                <div class="grid grid-cols-3 gap-3 text-center text-xs md:text-sm">
                  <div><p class="font-title text-xl leading-none mb-1"><?= $j['stats']['matches'] ?></p><p class="text-gray-400">Matchs</p></div>
                  <div><p class="font-title text-xl leading-none mb-1"><?= $j['stats']['minutes'] ?></p><p class="text-gray-400">Minutes</p></div>
                  <div><p class="font-title text-xl leading-none mb-1"><?= $j['stats']['points'] ?></p><p class="text-gray-400">Points</p></div>
                  <div><p class="font-title text-xl leading-none mb-1"><?= $j['stats']['essais'] ?></p><p class="text-gray-400">Essais</p></div>
                  <div><p class="font-title text-xl leading-none mb-1"><?= $j['stats']['distance'] ?></p><p class="text-gray-400">Km</p></div>
                  <div><p class="font-title text-xl leading-none mb-1"><?= $j['stats']['idp_avg'] ?></p><p class="text-gray-400">IDP Moy.</p></div>
                </div>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php elseif (!empty($_GET)): ?>
      <p class="text-center text-red-400 font-semibold">Veuillez sélectionner entre 2 et 10 joueurs.</p>
    <?php endif; ?>
  </main>
</div>

<script>
// Liste [id, nom complet] ------------------------------------------------------
const players   = <?= json_encode(array_map(fn($j) => ['id' => $j['id_joueur'], 'name' => $j['prenom_joueur'].' '.$j['nom_joueur']], $joueurs)) ?>;
const state     = <?= json_encode($_GET) ?>;
const container = document.getElementById('joueur-selects');
const nbInput   = document.getElementById('nb_joueurs');

function render() {
  const count = Math.max(2, Math.min(parseInt(nbInput.value) || 2, 10));
  container.innerHTML = '';

  for (let i = 1; i <= count; i++) {
    const wrap = document.createElement('div');
    wrap.className = 'flex items-center gap-2';

    const sel = document.createElement('select');
    sel.name = `joueur${i}`;
    sel.required = true;
    sel.className = 'bg-white/10 border border-white/20 backdrop-blur-md rounded-full px-4 py-2 text-sm text-black focus:outline-none appearance-none min-w-[200px]';

    const def = new Option(`Joueur ${i}`, '');
    def.disabled = true;
    def.selected = !state[`joueur${i}`] && !state[`joueurId${i}`];
    sel.add(def);

    players.forEach(p => {
      const o = new Option(p.name, p.name);
      if (state[`joueur${i}`] === p.name || state[`joueurId${i}`] == p.id) o.selected = true;
      o.dataset.id = p.id;
      sel.add(o);
    });

    // Appliquer le label anonymisé si besoin (au chargement)
    if (state[`anonymize${i}`]) {
      const selected = sel.options[sel.selectedIndex];
      if (selected) selected.textContent = 'Joueur Anonyme';
    }

    // Bouton anonymiser -------------------------------------------------------
    const anon = document.createElement('button');
    anon.type = 'button';
    anon.textContent = 'Anonymiser';
    anon.className = 'text-xs px-3 py-1 bg-white/20 rounded-full hover:bg-white/30 transition';

    if (state[`anonymize${i}`]) {
      // Déjà anonymisé ⇒ cacher bouton et ajouter champs cachés persistants
      anon.style.display = 'none';
      const hId = document.createElement('input');
      hId.type = 'hidden';
      hId.name = `joueurId${i}`;
      hId.value = state[`joueurId${i}`];
      wrap.appendChild(hId);

      const hAn = document.createElement('input');
      hAn.type = 'hidden';
      hAn.name = `anonymize${i}`;
      hAn.value = '1';
      wrap.appendChild(hAn);
    }

    anon.addEventListener('click', () => {
      const id = sel.options[sel.selectedIndex].dataset.id;
      const hiddenJ = document.createElement('input');
      hiddenJ.type = 'hidden';
      hiddenJ.name = `joueurId${i}`;
      hiddenJ.value = id;
      wrap.appendChild(hiddenJ);

      const hiddenAn = document.createElement('input');
      hiddenAn.type = 'hidden';
      hiddenAn.name = `anonymize${i}`;
      hiddenAn.value = '1';
      wrap.appendChild(hiddenAn);

      // Bouton supprimer -----------------------------------------------------------
const del = document.createElement('button');
del.type = 'button';
del.innerHTML = '&times;';
del.setAttribute('aria-label', 'Supprimer le joueur');
del.className = 'text-white/70 hover:text-red-400 text-xl leading-none';

del.addEventListener('click', () => {
  // Réinitialise la sélection de ce slot
  sel.selectedIndex = 0;
  delete state[`joueur${i}`];
  delete state[`joueurId${i}`];
  delete state[`anonymize${i}`];
  render();
});

wrap.appendChild(del);

      // Affichage select
      sel.options[sel.selectedIndex].textContent = 'Joueur Anonyme';

      // Mise à jour state & URL
      delete state[`joueur${i}`];
      state[`joueurId${i}`] = id;
      state[`anonymize${i}`] = '1';

      const url = new URL(window.location);
      url.searchParams.delete(`joueur${i}`);
      url.searchParams.set(`joueurId${i}`, id);
      url.searchParams.set(`anonymize${i}`, '1');
      history.replaceState(null, '', url);

      anon.remove();
    });

    sel.addEventListener('change', () => {
      state[`joueur${i}`] = sel.value;
      delete state[`joueurId${i}`];
      delete state[`anonymize${i}`];
      render();
    });

    wrap.appendChild(sel);
    wrap.appendChild(anon);
    container.appendChild(wrap);
  }

  // Désactivation des doublons (par id) --------------------------------------
  const sels = container.querySelectorAll('select');
  sels.forEach((s, idx) => {
    const chosenIds = Array.from(sels).filter((_, i) => i !== idx)
                              .map(s2 => s2.options[s2.selectedIndex].dataset.id);
    Array.from(s.options).forEach(o => {
      if (o.value && chosenIds.includes(o.dataset.id)) {
        o.disabled = true;
        o.classList.add('text-gray-400');
      } else {
        o.disabled = false;
        o.classList.remove('text-gray-400');
      }
    });
  });
}
render();
nbInput.addEventListener('input', render);
</script>
<script src="js/chat-widget.js" defer></script>
</body>
</html>
