<?php
session_start();
require_once 'db.php';
require_once 'auth.php';
$bdd = getBD();


// Lecture du filtre passé en GET (valeurs possibles : 'tout', 'actif', 'inactif')
$filtreActivite = $_GET['filtre_activite'] ?? 'tout';
$filtreActivite = in_array($filtreActivite, ['actif', 'inactif']) ? $filtreActivite : 'tout';

// Construction de la clause WHERE selon le filtre
$whereSql = '';
if ($filtreActivite === 'actif') {
  $whereSql = "WHERE j.activite = 'actif'";
} elseif ($filtreActivite === 'inactif') {
  $whereSql = "WHERE j.activite = 'inactif'";
}

$sql = "
  SELECT 
    j.id_joueur,
    j.nom_joueur,
    j.prenom_joueur,
    j.photo_path,
    j.poste,
    j.poste_secondaire,
    j.activite,
    GROUP_CONCAT(i.idp ORDER BY i.idp DESC SEPARATOR ',') AS idps,
    MAX(i.idp)                                           AS idp_max,
    ROUND(AVG(i.idp),1)                                   AS idp_avg
  FROM joueur AS j
  LEFT JOIN idp AS i ON i.id_joueur = j.id_joueur
  $whereSql
  GROUP BY j.id_joueur
  ORDER BY j.nom_joueur ASC
";


$result = $bdd->query($sql);
$current  = basename($_SERVER['PHP_SELF']);
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>DAT'ASBH – Joueurs</title>
  <link rel="icon" href="images/logo_asbh.png" />
  <!-- Tailwind CDN + thème couleur/police -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary : '#292E68',
            danger  : '#A00E0F'
          },
          fontFamily:{
            sans  : ['Inter','sans-serif'],
            title : ['"Bebas Neue"','cursive'],
            button: ['Manrope','sans-serif'],
          }
        }
      }
    };
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Bebas+Neue&family=Manrope:wght@500;600;700&display=swap" rel="stylesheet">
</head>

<body class="bg-gradient-to-br from-[#A00E0F] via-[#5e0000] to-black text-white font-sans min-h-screen">
<?php include 'sidebar.php'; 
if (!empty($_SESSION['toast'])): 
    $toast = $_SESSION['toast'];
    $toastType = $toast['type'] ?? 'success';
    $toastMessage = $toast['message'] ?? '';
    
    // Définir les classes CSS selon le type
    $toastClasses = [
        'success' => 'bg-green-500 border-green-400',
        'error' => 'bg-red-500 border-red-400',
        'warning' => 'bg-yellow-500 border-yellow-400',
        'info' => 'bg-blue-500 border-blue-400'
    ];
    
    $toastClass = $toastClasses[$toastType] ?? $toastClasses['success'];
    unset($_SESSION['toast']);
?>
    <!-- Toast fixe qui ne déplace pas le contenu -->
    <div id="toast" 
         class="fixed top-4 right-4 z-50 <?= $toastClass ?> text-white px-6 py-3 rounded-lg shadow-lg border-l-4 transform transition-all duration-300 ease-in-out translate-x-full opacity-0"
         style="min-width: 300px;">
        <div class="flex items-center justify-between">
            <span class="font-medium"><?= htmlspecialchars($toastMessage) ?></span>
            <button onclick="closeToast()" class="ml-4 text-white hover:text-gray-200 focus:outline-none">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <!-- Barre de progression -->
        <div class="mt-2 w-full bg-white/20 rounded-full h-1">
            <div id="toast-progress" class="bg-white h-1 rounded-full transition-all duration-100 ease-linear" style="width: 100%"></div>
        </div>
    </div>
<?php endif; ?>

<script>
// Script pour gérer le toast
document.addEventListener('DOMContentLoaded', function() {
    const toast = document.getElementById('toast');
    const progress = document.getElementById('toast-progress');
    
    if (toast) {
        // Animation d'apparition
        setTimeout(() => {
            toast.classList.remove('translate-x-full', 'opacity-0');
            toast.classList.add('translate-x-0', 'opacity-100');
        }, 100);
        
        // Barre de progression
        let width = 100;
        const duration = 2000; // 2 secondes
        const interval = 50; // Update toutes les 50ms
        const decrement = (100 / duration) * interval;
        
        const progressTimer = setInterval(() => {
            width -= decrement;
            if (progress) {
                progress.style.width = width + '%';
            }
            
            if (width <= 0) {
                clearInterval(progressTimer);
                closeToast();
            }
        }, interval);
        
        // Pause la barre de progression au survol
        toast.addEventListener('mouseenter', () => {
            clearInterval(progressTimer);
            if (progress) progress.style.animationPlayState = 'paused';
        });
        
        // Reprend la barre de progression
        toast.addEventListener('mouseleave', () => {
            if (progress) progress.style.animationPlayState = 'running';
        });
    }
});

function closeToast() {
    const toast = document.getElementById('toast');
    if (toast) {
        toast.classList.add('translate-x-full', 'opacity-0');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }
}
</script>
<!-- ╭─────────────────── BARRE TOP ───────────────────╮ -->
<header
  class="ml-0 md:ml-48 sticky top-0 z-10 w-[calc(100%-12rem)]
         flex items-center justify-between px-4 py-4
         bg-black/40 backdrop-blur shadow-md flex-wrap gap-4">

  <!-- Groupe de gauche : recherche + filtres -->
  <div class="flex flex-wrap items-center gap-4 w-full">

    <!-- Recherche -->
    <input id="searchInput" type="text" placeholder="Rechercher…"
       class="w-48 sm:w-60 px-3 py-1.5 rounded bg-white/20 placeholder-white focus:outline-none text-sm" />

    <!-- Filtres -->
    <div class="flex flex-wrap items-center gap-3 text-sm">

      <!-- Poste (sélection par tag) -->
       
      <div class="flex-col items-start gap-2">
        <label for="posteSelect">Poste :</label>
        <select id="posteSelect" class="text-black rounded p-1">
          <option value="" selected>Choisir…</option>
          <option>Pilier gauche</option>
          <option>Talonneur</option>
          <option>Pilier droit</option>
          <option>Deuxième ligne</option>
          <option>Troisième ligne</option>
          <option>Demi de mêlée</option>
          <option>Demi d'ouverture</option>
          <option>Centre</option>
          <option>Ailier</option>
          <option>Arrière</option>
        </select>
        <div id="selectedPostes" class="flex flex-col gap-1 mt-1 w-full"></div>
      </div>

      <!-- IDP min / max -->
      <label class="flex items-center gap-1 w-36">
        IDP min :
        <input id="ipMin" type="number" min="0" max="100" value="0"
               class="w-16 text-black rounded p-1 text-center">
      </label>
      <label class="flex items-center gap-1 w-36">
        IDP max :
        <input id="ipMax" type="number" min="0" max="100" value="100"
               class="w-16 text-black rounded p-1 text-center">
      </label>

      <!-- Filtre Activité -->
      <label class="flex items-center gap-1 w-36">
        Activité :
        <select id="activiteFilter" name="filtre_activite"
                class="text-black rounded p-1"
                onchange="onActiviteFilterChange(this.value)">
          <option value="tout"    <?= $filtreActivite === 'tout'    ? 'selected' : '' ?>>Tous</option>
          <option value="actif"   <?= $filtreActivite === 'actif'   ? 'selected' : '' ?>>Actifs</option>
          <option value="inactif" <?= $filtreActivite === 'inactif' ? 'selected' : '' ?>>Inactifs</option>
        </select>
      </label>

      <!-- Trier par poste / IDP -->
      <label class="flex items-center gap-1 w-42">
        Trier par poste :
        <select id="sortPoste" class="w-16 text-black rounded p-1">
          <option value="no">Non</option><option value="yes">Oui</option>
        </select>
      </label>
      <label class="flex items-center gap-1 w-42">
        Trier par IDP :
        <select id="sortIP" class="w-16 text-black rounded p-1">
          <option value="no">Non</option><option value="yes">Oui</option>
        </select>
      </label>

      <button onclick="resetFilters()"
        class="bg-white/20 text-white px-3 py-1 rounded hover:bg-white/30 transition">
        Réinitialiser filtres
      </button>
    </div>

  </div>
</header>


<!-- ╭─────────────────── GRILLE ─────────────────────╮ -->
<main class="ml-0 md:ml-48 p-6">
  <section id="grid"
           class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 xl:grid-cols-10 gap-2 max-w-7xl mx-auto">
<?php
while ($row = $result->fetch(PDO::FETCH_ASSOC)) : 
    $nomMaj = strtoupper($row['nom_joueur']);
    $prenom = $row['prenom_joueur'];
    $poste  = $row['poste'];
    $poste2 = $row['poste_secondaire'];
    $activ  = $row['activite'];

    /* nouvelles données --------------------------------------------- */
    $idpList = array_map('intval', explode(',', $row['idps'])); // ex. [85, 82, 70]
    $idpMax  = intval($row['idp_max']);
    $idpAvg  = floatval($row['idp_avg']);                        // ex. 85
    /* cercle = meilleure note (idpMax)                               */
    $pct     = max(0, min($idpMax, 100));
    $bgRing  = "conic-gradient(#e4041c {$pct}%, rgba(255,255,255,.15) {$pct}% 100%)";
    /* badge activité ------------------------------------------------ */
    $badgeClass = $activ === 'actif' ? 'bg-green-400' : 'bg-red-500';
?>
    <article
  data-name="<?= strtolower($prenom.' '.$row['nom_joueur']) ?>"
  data-last="<?= strtolower($row['nom_joueur']) ?>"
  data-poste="<?= strtolower($poste) ?>"
  data-idp="<?= $idp ?>"
  class="bg-black/30 rounded-xl p-3 text-center text-white shadow
         hover:shadow-lg hover:scale-[1.03] transition cursor-pointer relative"
  onclick="location.href='joueur.php?id=<?= $row['id_joueur'] ?>'">
  
  <!-- Pastille d’activité -->
  <span class="absolute top-2 right-2 w-3 h-3 rounded-full border-2 border-white <?= $badgeClass ?>"
        title="Statut: <?= $activ === 'actif' ? 'Actif' : 'Inactif' ?>"></span>

  <!-- Photo -->
  <img src="<?= htmlspecialchars($row['photo_path'] ?? '') ?>"
       onerror="this.src='images/default_avatar.jpg'"
       alt="<?= htmlspecialchars($prenom.' '.$nomMaj) ?>"
       class="w-20 h-20 object-cover rounded-full mx-auto mb-2">

  <!-- Nom / prénom -->
  <h2 class="text-sm font-extrabold tracking-wide truncate">
  <?= $nomMaj ?>
</h2>

  <p class="text-xs"><?= htmlspecialchars($prenom) ?></p>

  <!-- Poste(s) avec hauteur minimale -->
  <p class="text-[11px] italic text-gray-300 mt-1 min-h-[2rem]">
    <?= htmlspecialchars($poste) ?>
    <?php if ($poste2 !== ''): ?><br><?= htmlspecialchars($poste2) ?><?php endif; ?>
  </p>

  <!-- Cercle IDP -->
   <div class="relative w-14 h-14 mx-auto mt-2" style="background:<?= $bgRing ?>; border-radius:9999px;">
    <span class="absolute inset-0 flex items-center justify-center
                 text-lg font-extrabold"><?= $idpAvg ?></span>
  </div>

  <!-- Moyenne sous le cercle -->
<?php /*
<!-- Moyenne sous le cercle -->
<p class="text-[11px] text-gray-300 mt-0.5 italic">
    Moy : <?= $idpAvg ?>
</p>
*/ ?>


  <!-- Liste des autres valeurs IDP (petits badges) -->
  <?php if (count($idpList) > 1): ?>
    <div class="flex flex-wrap justify-center gap-1 mt-1 text-[10px]">
      <?php foreach ($idpList as $v): ?>
        <span class="px-1 bg-white/15 rounded"><?= $v ?></span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</article>
<?php endwhile; ?>

  </section>
</main>

<script>
  const cards      = [...document.querySelectorAll('article[data-name]')];
  const grid       = document.getElementById('grid');
  const els        = {
    search : document.getElementById('searchInput'),
    posteSelect : document.getElementById('posteSelect'),
    selectedContainer : document.getElementById('selectedPostes'),
    ipMin  : document.getElementById('ipMin'),
    ipMax  : document.getElementById('ipMax'),
    sortP  : document.getElementById('sortPoste'),
    sortIP : document.getElementById('sortIP')
  };

  // Tableau des postes sélectionnés
  let selectedPostes = [];

  // Ordre fixe des postes pour tri groupé
  const POSTE_ORDER = [
    "pilier gauche","talonneur","pilier droit","deuxième ligne",
    "troisième ligne","demi de mêlée","demi d'ouverture",
    "centre","ailier","arrière"
  ];

  // Initialisation : écouteurs
  els.search.addEventListener('input', filter);
  els.ipMin.addEventListener('input', filter);
  els.ipMax.addEventListener('input', filter);
  els.sortP.addEventListener('input', filter);
  els.sortIP.addEventListener('input', filter);
  els.posteSelect.addEventListener('change', onPosteSelect);

  // Quand on choisit un poste dans le <select>
  function onPosteSelect() {
    const choix = els.posteSelect.value;
    if (!choix) return;
    if (!selectedPostes.includes(choix)) {
      selectedPostes.push(choix);
      ajouterTag(choix);
      filter();
    }
    // Remet le <select> à "Choisir…"
    els.posteSelect.selectedIndex = 0;
  }

  // Ajoute visuellement un tag pour le poste choisi
function ajouterTag(poste) {
  const tag = document.createElement('span');
  // full width + justify-between pousse la croix à droite
  tag.className = "w-full bg-white/20 text-white rounded px-2 py-1 flex justify-between items-center text-xs";
  tag.dataset.poste = poste.toLowerCase();

  const label = document.createElement('span');
  label.textContent = poste;

  const btn = document.createElement('button');
  btn.type = 'button';
  btn.innerHTML = '&times;';
  btn.className = "text-sm leading-none";
  btn.onclick = () => removeTag(poste, tag);

  tag.appendChild(label);
  tag.appendChild(btn);
  els.selectedContainer.appendChild(tag);

  // désactiver l'option correspondante…
  Array.from(els.posteSelect.options).forEach(opt => {
    if (opt.value === poste) opt.disabled = true;
  });
}


  // Supprime un tag (et retire le poste du filtre)
  function removeTag(poste, tagElement) {
    // Retirer du tableau
    selectedPostes = selectedPostes.filter(p => p !== poste);
    // Retirer l'élément du DOM
    tagElement.remove();
    // Réactiver l'option dans le <select>
    Array.from(els.posteSelect.options).forEach(opt => {
      if (opt.value === poste) opt.disabled = false;
    });
    filter();
  }

  // Réinitialise tous les filtres
  function resetFilters() {
    els.search.value = '';
    els.ipMin.value   = 0;
    els.ipMax.value   = 100;
    els.sortP.value   = 'no';
    els.sortIP.value  = 'no';
    // Retirer tous les tags existants
    selectedPostes = [];
    els.selectedContainer.innerHTML = '';
    // Réactiver toutes les options du <select>
    Array.from(els.posteSelect.options).forEach(opt => {
      opt.disabled = false;
    });
    filter();
  }

  // Fonction de filtrage et d'affichage
  function filter() {
    const q        = els.search.value.trim().toLowerCase();
    const ipMin    = +els.ipMin.value || 0;
    const ipMax    = +els.ipMax.value || 100;
    const byPoste  = els.sortP.value === 'yes';
    const byIP     = els.sortIP.value === 'yes';

    // On filtre d'abord selon recherche, postes sélectionnés et idp
    let vis = cards.filter(c => {
      const idp = +c.dataset.idp;
      const posteCard = c.dataset.poste.toLowerCase();

      // Condition poste : si aucun poste sélectionné, tout passe,
      // sinon on vérifie que le poste de la carte est dans selectedPostes
      const okPoste = (selectedPostes.length === 0)
                    || (selectedPostes.map(p => p.toLowerCase()).includes(posteCard));

      return c.dataset.name.includes(q)
          && okPoste
          && idp >= ipMin && idp <= ipMax;
    });

    grid.innerHTML = '';

    if (byPoste) {
      // Regrouper par poste
      const groups = {};
      vis.forEach(c => {
        const key = c.dataset.poste;
        if (!groups[key]) groups[key] = [];
        groups[key].push(c);
      });

      Object.keys(groups)
        .sort((a, b) => POSTE_ORDER.indexOf(a) - POSTE_ORDER.indexOf(b))
        .forEach(g => {
          const bloc = groups[g];
          if (byIP) bloc.sort((a, b) => +b.dataset.idp - +a.dataset.idp);
          else      bloc.sort((a, b) => a.dataset.last.localeCompare(b.dataset.last, 'fr', { sensitivity: 'base' }));

          const h = document.createElement('h3');
          h.textContent = g.charAt(0).toUpperCase() + g.slice(1);
          h.className =
            "col-span-full flex items-center gap-2 text-white/80 font-normal text-sm mb-2";
          h.innerHTML = `<span>${h.textContent}</span><hr class="flex-1 border-white/30">`;
          grid.appendChild(h);

          bloc.forEach(card => grid.appendChild(card));
      });
    } else {
      if (byIP) vis.sort((a, b) => +b.dataset.idp - +a.dataset.idp);
      else vis.sort((a, b) => a.dataset.last.localeCompare(b.dataset.last, 'fr', { sensitivity: 'base' }));
      vis.forEach(card => grid.appendChild(card));
    }
  }
    function onActiviteFilterChange(val) {
    // Conserve le paramètre existant de recherche, tri, etc., et ajoute filtre_activite
    const url = new URL(window.location.href);
    url.searchParams.set('filtre_activite', val);
    window.location.href = url.toString();
  }

  // Premier affichage
  filter();
</script>
<a href="creer_joueur.php"
   class="fixed bottom-6 right-6 bg-white/30 hover:bg-[#292E68]
          text-white font-semibold px-4 py-2 rounded-full shadow-lg
          transition backdrop-blur-sm z-50">
  + Ajouter joueur
</a>

<script src="js/chat-widget.js" defer></script>
</body>
</html>
