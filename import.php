
<?php
require_once 'auth.php';
$message = '';
$valid = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Change les fichiers requis pour accepter des fichiers .xlsx
    $requiredFiles = ['xlsx1', 'xlsx2', 'xlsx3'];  // Nouvelle clé pour les fichiers Excel
    $uploadDir = 'uploads/'; // Assure-toi que ce dossier existe avec les bonnes permissions
    $uploadedFiles = [];
    $python = 'C:/Users/PC6/AppData/Local/Programs/Python/Python311/python.exe';


    foreach ($requiredFiles as $key) {
        if (!isset($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
            $message .= "<p class='text-red-400 font-semibold'>Erreur avec le fichier $key.</p>";
            $valid = false;
        } else {
            $fileTmp = $_FILES[$key]['tmp_name'];
            $fileName = $_FILES[$key]['name'];
            $ext = pathinfo($fileName, PATHINFO_EXTENSION);

            // Modifier la vérification pour les fichiers .xlsx
            if (strtolower($ext) !== 'xlsx') {
                $message .= "<p class='text-red-400 font-semibold'>$fileName n'est pas un fichier Excel valide (.xlsx).</p>";
                $valid = false;
            } else {
                $dest = $uploadDir . basename($fileName);
                move_uploaded_file($fileTmp, $dest);
                $uploadedFiles[$key] = $dest;
            }
        }
    }

    if ($valid) {
        $message = '<p class="text-green-400 font-semibold">Les fichiers Excel ont été importés avec succès !</p>';

        $scriptMapping = [
            'xlsx1' => 'python1.py',
            'xlsx2' => 'python1.py',
            'xlsx3' => 'python1.py'
        ];

        $messages = '';
        $outputDir = 'uploads';

        foreach ($uploadedFiles as $inputName => $filePath) {
            if (!isset($scriptMapping[$inputName])) {
                $messages .= "<p class='text-red-500'>Aucun script associé à $inputName.</p>";
                continue;
            }

            // On met à jour la commande pour accepter des fichiers .xlsx
            $pythonScript = escapeshellarg($scriptMapping[$inputName]);
            $inputFile = escapeshellarg($filePath);
            $outputFileName = pathinfo($filePath, PATHINFO_FILENAME) . '_modifie.xlsx';
            $outputFile = escapeshellarg($outputDir . '/' . $outputFileName);

            // Construction de la commande pour exécuter le script Python avec le fichier Excel
            // Assurez-vous qu'il n'y a pas de guillemets supplémentaires
            $command = $python . ' ' . $pythonScript . ' ' . $inputFile . ' ' . $outputFile . ' 2>&1';

            // Déboguer en affichant la commande (si nécessaire)
            // echo "<p>Command: $command</p>";

            $result = shell_exec($command);

            $messages .= "<p class='text-green-400 font-semibold'>$inputName transformé : $outputFileName</p>";
           
        }

        $message .= $messages;
    }
    

$output = shell_exec($python . ' import_to_db.py 2>&1');
$message .= "<p class='text-green-400 font-semibold'>Fichiers importés dans la base de données.</p>";


}


       // le code python MANON qui fera en sorte que ça se transforme

//if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    //$csvFile = $_FILES['csv_file']['tmp_name'];

    // Déplacer dans un dossier temporaire (optionnel)
    //$destination = 'uploads/original.csv';
    //move_uploaded_file($csvFile, $destination);

    // Lancer le script Python qui transforme ET insère dans la BDD
    //$output = shell_exec("python3 transform_and_import.py " . escapeshellarg($destination));

    //echo "<p>Import terminé :</p><pre>$output</pre>";
//}


?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Import CSV</title>
  <link rel="icon" type="image/png" href="images/logo_asbh.png" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

  <script>
    // Fonction pour afficher le nom du fichier
    function updateFileName(inputId, labelId) {
      var input = document.getElementById(inputId);
      var label = document.getElementById(labelId);
      
      // Vérifie si un fichier a été sélectionné
      if (input.files && input.files[0]) {
        label.textContent = "Fichier sélectionné : " + input.files[0].name; // Affiche le nom du fichier
      } else {
        label.textContent = "Aucun fichier sélectionné";
      }
    }
  </script>
</head>
<body class="bg-gradient-to-br from-[#A00E0F] via-[#5e0000] to-black text-white font-['Inter'] min-h-screen flex">
  <?php include 'sidebar.php'; ?>

  <div class="flex-1 ml-64"> <!-- Ajuste ml-64 selon la largeur réelle de ta sidebar -->
    <header class="flex flex-col items-center space-y-4 py-6">
      <a href="index.php" class="flex flex-col items-center gap-2 hover:opacity-70 transition">
          <img src="images/logo_asbh.png" alt="ASBH" class="w-24 h-auto object-contain" />
        </a>
      <h1 class="text-2xl font-bold">Importer les données</h1>
    </header>

    <main class="max-w-xl mx-auto bg-white/10 backdrop-blur-md rounded-xl shadow-lg p-8 text-white mt-6">
      <?= $message ?>

      <form action="" method="post" enctype="multipart/form-data" class="flex flex-col gap-6 mt-4">
        <!-- Data Match -->
        <label class="flex flex-col items-center px-4 py-6 bg-white/10 text-white rounded-xl shadow-inner tracking-wide border border-white/20 cursor-pointer hover:bg-white/20 transition-all">
          <span class="text-lg font-semibold">Data Match</span>
          <input type="file" name="xlsx1" id="xlsx1" accept=".xlsx" class="hidden" required onchange="updateFileName('xlsx1', 'file1-name')">
        </label>
        <span id="file1-name" class="text-white text-sm"></span>

        <!-- Statistiques match -->
        <label class="flex flex-col items-center px-4 py-6 bg-white/10 text-white rounded-xl shadow-inner tracking-wide border border-white/20 cursor-pointer hover:bg-white/20 transition-all">
          <span class="text-lg font-semibold">Statistiques match</span>
          <input type="file" name="xlsx2" id="xlsx2" accept=".xlsx" class="hidden" required onchange="updateFileName('xlsx2', 'file2-name')">
        </label>
        <span id="file2-name" class="text-white text-sm"></span>

        <!-- Performances -->
        <label class="flex flex-col items-center px-4 py-6 bg-white/10 text-white rounded-xl shadow-inner tracking-wide border border-white/20 cursor-pointer hover:bg-white/20 transition-all">
          <span class="text-lg font-semibold">Performances (Rapport GPS)</span>
          <input type="file" name="xlsx3" id="xlsx3" accept=".xlsx" class="hidden" required onchange="updateFileName('xlsx3', 'file3-name')">
        </label>
        <span id="file3-name" class="text-white text-sm"></span>

        <button type="submit" class="bg-white/20 hover:bg-white/30 text-white font-semibold px-6 py-3 rounded-full shadow-lg backdrop-blur-md transition-all">
          Valider
        </button>
      </form>
    </main>
  </div>
</body>
</html>
