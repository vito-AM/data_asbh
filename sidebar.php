<?php $current = basename($_SERVER['PHP_SELF']); ?>

<aside id="sidebar" class="fixed inset-y-0 left-0 w-48 z-20 shadow-lg
              h-screen flex flex-col text-gray-100
              divide-y divide-white/15
              bg-gradient-to-b from-[#292E68] via-[#1f2355] to-[#0e1128]">

<!-- Zone 1 : Logo -->
<div class="flex-1 flex flex-col items-center justify-center px-3 pb-2">
  <a href="index.php" class="flex flex-col items-center gap-1 hover:opacity-70 transition">
    <img src="images/logo_asbh.png" alt="ASBH" class="w-20 h-auto object-contain" />
    <span class="text-center text-sm font-semibold leading-tight">
      DAT'ASBH
    </span>
  </a>
</div>


  <!-- Zones 2 à 5 : liens plein bloc -->
  <a href="joueurs.php"
     class="flex-1 flex items-center justify-center w-full py-3 text-lg text-center rounded-xl
            hover:bg-black/20 hover:-translate-y-0.5 hover:shadow-md transition-all duration-200
            <?= $current === 'joueurs.php' ? 'bg-white/30 font-bold' : '' ?>">
    JOUEURS
  </a>

  <a href="matchs.php"
     class="flex-1 flex items-center justify-center w-full py-3 text-lg text-center rounded-xl
            hover:bg-black/20 hover:-translate-y-0.5 hover:shadow-md transition-all duration-200
            <?= $current === 'matchs.php' ? 'bg-white/30 font-bold' : '' ?>">
    MATCHS
  </a>

  <a href="face.php"
     class="flex-1 flex items-center justify-center w-full py-3 text-lg text-center rounded-xl
            hover:bg-black/20 hover:-translate-y-0.5 hover:shadow-md transition-all duration-200
            <?= $current === 'face.php' ? 'bg-white/30 font-bold' : '' ?>">
    FACE À FACE
  </a>

  <a href="import.php"
     class="flex-1 flex items-center justify-center w-full py-3 text-lg text-center rounded-xl
            hover:bg-black/20 hover:-translate-y-0.5 hover:shadow-md transition-all duration-200
            <?= $current === 'import.php' ? 'bg-white/30 font-bold' : '' ?>">
    IMPORTER
  </a>

<div class="mt-auto p-4 text-center">
  <a href="gestion_utilisateurs.php"
    class="flex-1 flex items-center justify-center w-full py-3 text-lg text-center rounded-xl
            hover:bg-black/20 hover:-translate-y-0.5 hover:shadow-md transition-all duration-200
            <?= $current === 'gestion_utilisateurs.php' ? 'bg-white/30 font-bold' : '' ?>">
    Gestion des utilisateurs
  </a>
  <a href="logout.php"
     onclick="return confirm('Êtes-vous sûr de vouloir vous déconnecter ?');"
     class="inline-block text-red-400 hover:text-red-600 font-bold text-sm">
    Déconnexion
  </a>
</div>



</aside>
