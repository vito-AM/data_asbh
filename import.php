<?php
session_start();
require_once 'auth.php';
$message = '';
$valid   = true;

/**
 * 1) Détection dynamique de l’exécutable Python
 */
$python = getenv('PYTHON_PATH'); // Permet d'overrider via var d'env

if (!$python) {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows : récupère la liste, puis garde le premier chemin
        $raw = trim(shell_exec('where python 2>NUL'));
        $candidates = preg_split("/\r\n|\n|\r/", $raw);
        $python = $candidates[0] ?: 'python';
    } else {
        // Unix/macOS : essaie python3 puis python
        $raw3 = trim(shell_exec('which python3 2>/dev/null') ?? '');
        $raw  = trim(shell_exec('which python 2>/dev/null') ?? '');
        $c3 = preg_split("/\r\n|\n|\r/", $raw3);
        $c  = preg_split("/\r\n|\n|\r/", $raw);
        if (!empty($c3[0])) {
            $python = $c3[0];
        } elseif (!empty($c[0])) {
            $python = $c[0];
        } else {
            $python = 'python';
        }
    }
}

/**
 * 2) Traitement du POST pour les .xlsx
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requiredFiles = ['xlsx1', 'xlsx2', 'xlsx3'];
    $uploadDir     = __DIR__ . '/uploads';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $uploadedFiles = [];

    // 2a) Upload & vérification
    foreach ($requiredFiles as $key) {
        if (!isset($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
            $message .= "<p class='text-red-400 font-semibold'>Erreur avec le fichier « {$key} ».</p>";
            $valid = false;
            continue;
        }
        $tmp  = $_FILES[$key]['tmp_name'];
        $name = $_FILES[$key]['name'];
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($ext !== 'xlsx') {
            $message .= "<p class='text-red-400 font-semibold'>{$name} : format invalide (attendu .xlsx).</p>";
            $valid = false;
            continue;
        }

        $dest = $uploadDir . '/' . basename($name);
        if (!move_uploaded_file($tmp, $dest)) {
            $message .= "<p class='text-red-400 font-semibold'>Impossible de déplacer {$name}.</p>";
            $valid = false;
        } else {
            $uploadedFiles[$key] = $dest;
        }
    }

    /**
     * 3) Exécution des scripts Python si tout est OK
     */
    if ($valid) {
        chdir(__DIR__); // On se place toujours dans le dossier du projet

        // 3a) Transformations via python1.py
        foreach ($uploadedFiles as $inputName => $inputPath) {
            $baseName      = pathinfo($inputPath, PATHINFO_FILENAME);
            $outputPath    = $uploadDir . '/' . $baseName . '_modifie.xlsx';
            $scriptTransfo = __DIR__ . '/python1.py';

            $inEsc   = escapeshellarg($inputPath);
            $outEsc  = escapeshellarg($outputPath);
            $script  = escapeshellarg($scriptTransfo);
            $cmd     = "\"{$python}\" {$script} {$inEsc} {$outEsc} 2>&1";

            exec($cmd, $lines, $code);
            $message .= "<pre>Transform ({$inputName}): {$cmd}\n"
                      ."Code retour: {$code}\n"
                      .implode("\n", $lines)
                      ."</pre>";

            if ($code !== 0) {
                $message .= "<p class='text-red-500 font-semibold'>Échec de la transformation de {$inputName}.</p>";
                $valid = false;
                break;
            } else {
                $message .= "<p class='text-green-400 font-semibold'>{$inputName} → "
                          .basename($outputPath)." généré avec succès.</p>";
            }
        }
    }

    // 3b) Import en base via import_to_db.py
    if ($valid) {
        $scriptImport = __DIR__ . '/import_to_db.py';
        $scriptEsc    = escapeshellarg($scriptImport);
        $cmd2         = "\"{$python}\" {$scriptEsc} 2>&1";

        exec($cmd2, $lines2, $code2);
        $message .= "<pre>Import DB: {$cmd2}\n"
                  ."Code retour: {$code2}\n"
                  .implode("\n", $lines2)
                  ."</pre>";

        if ($code2 === 0) {
        // Au lieu de : $message .= "<p class='text-green-400'>Import OK</p>";
        $_SESSION['toast'] = [
          'type'    => 'success',
          'message' => 'Import Excel terminé avec succès !'
        ];
        // Pour éviter le “resubmit” du formulaire, on redirige vers la même page
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $_SESSION['toast'] = [
          'type'    => 'error',
          'message' => 'Échec de l’import en base.'
        ];
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>DAT'ASBH - Import Excel</title>
  <link rel="icon" href="images/logo_asbh.png" />
  <script src="https://cdn.tailwindcss.com"></script>
  <?php include 'tailwind_setup.php'; ?>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <script>
    function updateFileName(inputId, labelId) {
      var input = document.getElementById(inputId);
      var label = document.getElementById(labelId);
      label.textContent = input.files && input.files[0]
        ? "Fichier sélectionné : " + input.files[0].name
        : "Aucun fichier sélectionné";
    }
  </script>
</head>
<body class="bg-gradient-to-br from-[#292E68] via-[#1f2355] to-black text-white font-sans min-h-screen">
  <?php include 'sidebar.php'; ?>

  <?php if (!empty($_SESSION['toast'])): 
    $toast = $_SESSION['toast'];
    $type  = $toast['type']    ?? 'info';
    $msg   = $toast['message'] ?? '';
    unset($_SESSION['toast']);
    // Classes Tailwind par type
    $c = [
      'success' => 'bg-green-500 border-green-400',
      'error'   => 'bg-red-500   border-red-400',
      'warning' => 'bg-yellow-500 border-yellow-400',
      'info'    => 'bg-blue-500  border-blue-400'
    ][$type];
?>
  <div id="toast"
       class="fixed top-4 right-4 z-50 <?= $c ?> text-white px-6 py-3 rounded-lg shadow-lg border-l-4 transform transition-all duration-300 ease-in-out translate-x-full opacity-0"
       style="min-width:300px">
    <div class="flex items-center justify-between">
      <span class="font-medium"><?= htmlspecialchars($msg) ?></span>
      <button onclick="closeToast()" class="ml-4 text-white hover:text-gray-200">
        &times;
      </button>
    </div>
    <div class="mt-2 w-full bg-white/20 rounded-full h-1">
      <div id="toast-progress" class="bg-white h-1 rounded-full transition-all duration-100 ease-linear" style="width:100%"></div>
    </div>
  </div>
<?php endif; ?>


  <div class="flex-1 ml-64">
    <header class="flex flex-col items-center space-y-4 py-6">
      <a href="index.php" class="hover:opacity-70 transition">
      </a>
      <h1 class="text-3xl md:text-4xl font-bold">Importer les données Excel</h1>
      <p class="mt-2 text-sm text-white/80 max-w-md text-center">
  Sélectionne les trois fichiers .xlsx&nbsp;–&nbsp;<em>Data Match</em>, <em>Export Stats Match </em> et
  <em>Rapport GPS</em> – puis clique sur « Valider ».  
  Chaque fichier doit être au format Excel (.xlsx).
</p>

    </header>

    <main class="max-w-xl mx-auto bg-white/10 backdrop-blur-md rounded-xl shadow-lg p-8 text-white mt-6">
      <?= $message ?>

      <form action="" method="post" enctype="multipart/form-data" class="flex flex-col gap-6 mt-4">
        <?php
          $labels = [
            'xlsx1' => 'Data Match',
            'xlsx2' => 'Statistiques Match',
            'xlsx3' => 'Performances (Rapport GPS)'
          ];
          foreach ($labels as $key => $label): ?>
          <label class="flex flex-col items-center px-4 py-6 bg-white/10 rounded-xl border cursor-pointer hover:bg-white/20 transition">
            <span class="text-lg font-semibold"><?= $label ?></span>
            <input type="file"
                   name="<?= $key ?>"
                   id="<?= $key ?>"
                   accept=".xlsx"
                   class="hidden"
                   required
                   onchange="updateFileName('<?= $key ?>','lbl-<?= $key ?>')">
          </label>
          <span id="lbl-<?= $key ?>" class="text-white text-sm"></span>
        <?php endforeach; ?>

        <button type="submit" class="bg-white/20 hover:bg-white/30 text-white font-semibold px-6 py-3 rounded-full shadow-lg transition">
          Valider
        </button>
      </form>
    </main>
  </div>
  <script>
// Affiche/anim le toast
document.addEventListener('DOMContentLoaded', () => {
  const toast    = document.getElementById('toast');
  const progress = document.getElementById('toast-progress');
  if (!toast) return;

  // apparition
  setTimeout(() => {
    toast.classList.remove('translate-x-full','opacity-0');
    toast.classList.add('translate-x-0','opacity-100');
  }, 100);

  // barre de progression
  let width    = 100;
  const total  = 2000; // ms
  const step   = 50;
  const delta  = (100/total)*step;
  let timer = setInterval(() => {
    width -= delta;
    progress.style.width = width + '%';
    if (width <= 0) {
      clearInterval(timer);
      closeToast();
    }
  }, step);

  // pause/reprise au survol
  toast.addEventListener('mouseenter', () => clearInterval(timer));
  toast.addEventListener('mouseleave', () => {
    timer = setInterval(() => {
      width -= delta;
      progress.style.width = width + '%';
      if (width <= 0) {
        clearInterval(timer);
        closeToast();
      }
    }, step);
  });
});

function closeToast() {
  const t = document.getElementById('toast');
  if (!t) return;
  t.classList.add('translate-x-full','opacity-0');
  setTimeout(() => t.remove(), 300);
}
</script>

</body>
</html>
