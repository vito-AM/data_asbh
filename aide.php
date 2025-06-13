<?php 
require_once 'auth.php';
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>ASBH – Aide & Crédits</title>
  <link rel="icon" type="image/png" href="images/logo_asbh.png" />

  <script src="https://cdn.tailwindcss.com"></script>
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
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Bebas+Neue&family=Manrope:wght@500;600;700&display=swap" rel="stylesheet">
</head>

<body class="font-sans text-gray-900">

  <?php include 'sidebar.php'; ?>

  <!-- fond flouté -->
  <div class="fixed inset-0 -z-10">
    <img src="images/fond_accueil.jpg" alt="" class="w-full h-full object-cover" />
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
  </div>

  <main class="min-h-screen flex items-center justify-center ml-0 md:ml-48 px-6">
  <div class="bg-white/10 backdrop-blur-lg p-10 rounded-xl text-white max-w-3xl w-full space-y-8 shadow-xl">

    <!-- Titre -->
    <h1 class="text-4xl font-title uppercase tracking-wide text-center">Aide & Crédits</h1>

    <!-- Section Aide -->
    <section class="space-y-3">
      <h2 class="text-xl font-semibold text-[#A00E0F]">Besoin d’aide ?</h2>
      <p>
        Cette plateforme a été conçue pour explorer les performances de l’AS Béziers Hérault de façon interactive.
        Elle vous permet d’accéder aux statistiques des joueurs, de visualiser leur position sur le terrain,
        d’analyser les détails des matchs, de comparer plusieurs profils et même d’importer vos propres données.
      </p>
      <ul class="list-disc list-inside text-white/90 space-y-1">
        <li><strong>Joueurs</strong> : fiches individuelles avec données et indicateurs clés.</li>
        <li><strong>Matchs</strong> : détails et statistiques des rencontres passées.</li>
        <li><strong>Face à face</strong> : comparaison de deux ou plusieurs joueurs côte à côte.</li>
        <li><strong>Importer</strong> : ajout de données personnalisées au format CSV.</li>
      </ul>
    </section>

    <!-- Section Crédits -->
    <section class="space-y-4 pt-6 border-t border-white/20">
  <h2 class="text-2xl font-semibold text-[#A00E0F]">Crédits</h2>
  <p class="text-white/90">
    Ce projet a été réalisé dans le cadre d’un stage au sein de <strong>DELL Technologies</strong> par des étudiants de <strong>L3 MIASHS – Université Paul Valéry</strong>, en collaboration avec l’<strong>Association Sportive Béziers Hérault</strong>.
    Il s’inscrit dans un partenariat visant à exploiter les données de performance pour l’équipe Élite Crabos.
  </p>
  <ul class="list-disc list-inside text-white/90 space-y-2">
    <li class="flex items-center gap-3">
      Partenaires : 
      <strong>DELL Technologies</strong>
      <img src="images/dell.png" alt="Logo DELL" class="h-10">
      <strong>ASBH</strong>
      <img src="images/logo_asbh.png" alt="Logo ASBH" class="h-10">
    </li>
    <li class="flex items-center gap-3">
      Encadrement pédagogique :
      <strong>Université Paul Valéry – L3 MIASHS</strong>
      <img src="images/paulva.png" alt="Logo Université Paul Valéry" class="h-12">
    </li>
  </ul>
</section>

<section class="space-y-2 pt-6 border-t border-white/20">
  <h2 class="text-2xl font-semibold text-[#A00E0F]">Contact</h2>
  <p class="text-white/90">
    Pour toute question ou suggestion, vous pouvez contacter :
  </p>
  <ul class="text-white/90 space-y-1">
    <li><a href="mailto:aya.toukdaoui@etu.univ-montp3.fr" class="underline hover:text-white">aya.toukdaoui@etu.univ-montp3.fr</a></li>
    <li><a href="mailto:hugo.marchionni@etu.univ-montp3.fr" class="underline hover:text-white">hugo.marchionni@etu.univ-montp3.fr</a></li>
    <li><a href="mailto:manon.stingre@etu.univ-montp3.fr" class="underline hover:text-white">manon.stingre@etu.univ-montp3.fr</a></li>
  </ul>
</section>


    <!-- Bouton retour -->
    <div class="text-center pt-6">
      <a href="index.php" class="inline-block px-5 py-2 bg-white/80 text-black font-semibold rounded-md hover:bg-white transition">
        ↩ Retour à l'accueil
      </a>
    </div>

  </div>
</main>


</body>
</html>
