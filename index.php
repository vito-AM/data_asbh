<?php /* index.php */
require_once 'auth.php'; ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>DAT'ASBH – Accueil</title>
  <link rel="icon" href="images/logo_asbh.png" />


  <!-- Tailwind 3 CDN -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Config : couleurs de marque -->
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary:      '#292E68',
            primaryDark:  '#1f2355',
            danger:       '#A00E0F',
            dangerDark:   '#880c0d',
          },
          fontFamily: {
            sans:   ['Inter', 'sans-serif'],
            title:  ['"Bebas Neue"', 'cursive'],
            button: ['Manrope', 'sans-serif'],
          }
        }
      }
    }
  </script>

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Bebas+Neue&family=Manrope:wght@500;600;700&display=swap" rel="stylesheet">
</head>

<body class="font-sans text-gray-900 text-lg md:text-xl">

  <?php include 'sidebar.php'; ?>

  <!-- fond flouté -->
  <div class="fixed inset-0 -z-10">
    <img src="images/fond_accueil.jpg" alt=""
         class="w-full h-full object-cover" />
    <div class="absolute inset-0 bg-black/45 backdrop-blur-sm"></div>
  </div>

  <!-- contenu -->
  <main class="h-screen flex items-center justify-center ml-0 md:ml-48">
    <div class="text-center max-w-2xl px-6 space-y-10">

<h1 class="flex flex-col items-center font-title text-6xl uppercase tracking-wide text-white px-6 py-4 rounded-md">
  <img src="images/logo_asbh.png" alt="ASBH" class="w-36 h-auto object-contain" />
  DAT'ASBH
</h1>

      <p class="leading-relaxed text-white">
        Bienvenue sur la plateforme d’analyse des performances de l’équipe Élite Crabos de l’Association Sportive Béziers Hérault ! <br><br>
        Accédez aux statistiques détaillées des joueurs, consultez les résultats des matchs, comparez les performances en face-à-face et importez vos propres données pour une analyse personnalisée.
      </p>

      <footer class="text-base text-white pt-8">
        © <?=date('Y')?> ASBH – Tous droits réservés | 
        <a href="aide.php" class="underline hover:text-gray-100">Aide & crédits</a>
      </footer>

    </div>
  </main>

</body>
</html>
