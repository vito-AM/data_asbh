<<<<<<< HEAD
<?php
require_once 'db.php';
require_once 'auth.php';
$bdd = getBD();

// Récupération de la liste des joueurs
$joueurs = $bdd->query("SELECT nom_joueur, prenom_joueur FROM joueur ORDER BY nom_joueur, prenom_joueur")->fetchAll();

// Récupération des joueurs sélectionnés dynamiquement
$joueurs_selectionnes = [];
$nb_joueurs = isset($_GET['nb_joueurs']) ? max(2, min((int)$_GET['nb_joueurs'], 10)) : 2;

for ($i = 1; $i <= $nb_joueurs; $i++) {
    if (!empty($_GET["joueur$i"])) {
        $stmt = $bdd->prepare("SELECT * FROM joueur WHERE CONCAT(prenom_joueur, ' ', nom_joueur) = ?");
        $stmt->execute([$_GET["joueur$i"]]);
        $joueur = $stmt->fetch();
        if ($joueur) {
            if (isset($_GET["anonymize$i"])) {
                $joueur['anonyme'] = true;
            }
            $joueurs_selectionnes[] = $joueur;
        }
    }
}

// Vérification de doublons
$selectedNames = array_map(fn($j) => $j['prenom_joueur'] . ' ' . $j['nom_joueur'], $joueurs_selectionnes);
if (count(array_unique($selectedNames)) < count($selectedNames)) {
    echo '<p class="text-center text-red-400 font-semibold mb-6">Veuillez sélectionner des joueurs différents.</p>';
    $joueurs_selectionnes = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Comparaison Joueurs</title>
  <link rel="icon" type="image/png" href="images/logo_asbh.png" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" type="text/css" href="stylesacc.css" />
</head>
<body class="bg-gradient-to-br from-[#A00E0F] via-[#5e0000] to-black text-white font-['Inter'] min-h-screen flex">
<?php include 'sidebar.php'; ?>

<div class="flex-1 ml-64">
<header class="flex flex-col items-center space-y-4 py-6">
<a href="index.php" class="flex flex-col items-center gap-2 hover:opacity-70 transition">
  <img src="images/logo_asbh.png" alt="ASBH" class="w-24 h-auto object-contain" />
</a>
<h1 class="text-3xl font-bold">FACE À FACE MULTI-JOUEURS</h1>
</header>

<main class="max-w-6xl mx-auto p-8">
<form method="get" class="flex flex-col items-center gap-4 mb-10">
  <label class="text-white font-semibold">Nombre de joueurs à comparer :
  <input type="number" name="nb_joueurs" id="nb_joueurs" min="2" max="10"
         value="<?= htmlspecialchars($nb_joueurs) ?>"
         class="bg-white/10 text-white border border-white/20 rounded px-3 py-1 w-20 text-center"
         onchange="updatePlayerSelects()">
</label>

  <div id="joueur-selects" class="flex flex-wrap justify-center gap-4 mt-4 w-full"></div>

  <button type="submit" class="bg-white/20 hover:bg-white/30 text-white font-semibold px-6 py-2 rounded-full shadow-lg backdrop-blur-md transition-all">
    Comparer
  </button>
</form>

<?php if (!empty($joueurs_selectionnes)): ?>
  <div class="grid grid-cols-1 md:grid-cols-<?= min(3, count($joueurs_selectionnes)) ?> gap-8">
    <?php foreach ($joueurs_selectionnes as $j): ?>
      <a href="joueur.php?nom=<?= urlencode($j['nom_joueur']) ?>&prenom=<?= urlencode($j['prenom_joueur']) ?>" class="block">
        <div class="bg-white/10 backdrop-blur-md rounded-xl shadow-lg p-6 text-white">
          <div class="flex items-center gap-4 border-b border-white/20 pb-4 mb-4">
            <img src="<?= isset($j['anonyme']) ? 'images/avatar_generic.png' : htmlspecialchars($j['photo_path']) ?>" alt="Photo"
                 class="w-24 h-24 rounded-full border-2 border-white object-cover">
            <div>
              <h2 class="text-xl font-bold">
                <?= isset($j['anonyme']) ? 'Joueur Anonyme' : htmlspecialchars($j['prenom_joueur'] . ' ' . $j['nom_joueur']) ?>
              </h2>
              <p class="text-red-300 font-medium"><?= htmlspecialchars($j['poste']) ?></p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2 text-sm">
            <div><span class="text-gray-300">Âge:</span> <span class="text-black"><?= date_diff(date_create($j['date_naissance']), date_create('today'))->y ?> ans</span></div>
            <div><span class="text-gray-300">Taille:</span> <span class="text-black"><?= htmlspecialchars($j['taille_cm']) ?> cm</span></div>
            <div><span class="text-gray-300">Poids:</span> <span class="text-black"><?= htmlspecialchars($j['poids_kg']) ?> kg</span></div>
            <div><span class="text-gray-300">Poste secondaire:</span> <span class="text-black"><?= htmlspecialchars($j['poste_secondaire']) ?></span></div>
            <div><span class="text-gray-300">Indice Performance:</span> <span class="text-black"><?= htmlspecialchars($j['idp']) ?></span></div>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php elseif (isset($_GET['nb_joueurs'])): ?>
  <p class="text-center text-red-300 mt-6">Veuillez sélectionner entre 2 et 10 joueurs différents.</p>
<?php endif; ?>
</main>
</div>

<script>
  const joueurs = <?= json_encode(array_map(fn($j) => $j['prenom_joueur'] . ' ' . $j['nom_joueur'], $joueurs)) ?>;
  const oldValues = <?= json_encode($_GET) ?>;

  const joueurSelectsContainer = document.getElementById('joueur-selects');
  const nbInput = document.getElementById('nb_joueurs');

  function updatePlayerSelects() {
  const count = parseInt(nbInput.value) || 2;
  joueurSelectsContainer.innerHTML = "";

  const selectedValues = [];

  for (let i = 0; i < count; i++) {
    const div = document.createElement("div");
    div.className = "flex items-center gap-2";

    const select = document.createElement("select");
    select.name = `joueur${i + 1}`;
    select.required = true;
    select.className = "bg-white/10 text-black border border-white/20 backdrop-blur-md px-4 py-2 rounded-full shadow-inner appearance-none pr-10 focus:outline-none";

    const optionDefault = document.createElement("option");
    optionDefault.text = `Joueur ${i + 1}`;
    optionDefault.value = "";
    optionDefault.disabled = true;
    optionDefault.selected = !oldValues[`joueur${i + 1}`];
    select.appendChild(optionDefault);

    joueurs.forEach(nom => {
      const opt = document.createElement("option");
      opt.value = nom;
      opt.textContent = nom;

      if (oldValues[`joueur${i + 1}`] === nom) {
  opt.selected = true;
  selectedValues[i] = nom;

  // ✅ Si le joueur est anonymisé, changer le texte affiché
  if (oldValues[`anonymize${i + 1}`]) {
    opt.textContent = "Joueur Anonyme";
  }
}

      select.appendChild(opt);
    });

    const anonBtn = document.createElement("button");
    anonBtn.type = "button";
    anonBtn.textContent = "Anonymiser";
    anonBtn.className = "px-3 py-1 text-sm bg-white/20 rounded-full hover:bg-white/30 transition";
    anonBtn.addEventListener("click", () => {
  const hidden = document.createElement("input");
  hidden.type = "hidden";
  hidden.name = `anonymize${i + 1}`;
  hidden.value = "1";
  div.appendChild(hidden);

  oldValues[`anonymize${i + 1}`] = "1";
  
  
  // Modifier dynamiquement l'option sélectionnée
  const selectedOption = select.options[select.selectedIndex];
  if (selectedOption) {
    selectedOption.textContent = "Joueur Anonyme";
  }

  // ✅ Mise à jour de l'URL pour conserver l'anonymisation
  const url = new URL(window.location);
  url.searchParams.set(`anonymize${i + 1}`, "1");
  history.replaceState(null, "", url);

  anonBtn.remove();
});



    select.addEventListener("change", () => {
      oldValues[`joueur${i + 1}`] = select.value;
      updatePlayerSelects(); // re-render all to update disabled options
    });

    div.appendChild(select);
    if (!oldValues[`anonymize${i + 1}`]) {
  div.appendChild(anonBtn);
}

    joueurSelectsContainer.appendChild(div);
  }

  // Deuxième passe : griser les options déjà choisies ailleurs
  const selects = joueurSelectsContainer.querySelectorAll("select");
  selects.forEach((select, idx) => {
    const currentValue = select.value;
    const usedNames = Array.from(selects)
      .filter((_, i) => i !== idx)
      .map(s => s.value)
      .filter(Boolean);

    for (let option of select.options) {
      if (option.value !== currentValue && usedNames.includes(option.value)) {
        option.disabled = true;
        option.classList.add("text-gray-400");
      } else {
        option.disabled = false;
        option.classList.remove("text-gray-400");
      }
    }
  });
}


  updatePlayerSelects();

  nbInput.addEventListener('change', updatePlayerSelects);
</script>

</body>
</html>
