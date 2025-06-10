-- phpMyAdmin SQL Dump
-- version 5.1.2
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost:3306
-- Généré le : ven. 16 mai 2025 à 07:12
-- Version du serveur : 5.7.24
-- Version de PHP : 8.3.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `stage`
--

-- --------------------------------------------------------

--
-- Structure de la table `courir`
--

CREATE TABLE `courir` (
  `id_joueur` int(11) NOT NULL,
  `id_match` int(11) NOT NULL,
  `temps_de_jeu` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `equipe`
--

CREATE TABLE `equipe` (
  `id_equipe` int(11) NOT NULL,
  `nom_equipe` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Déchargement des données de la table `equipe`
--

INSERT INTO `equipe` (`id_equipe`, `nom_equipe`) VALUES
(1, 'ASBH'),
(2, 'CAR'),
(3, 'MTP');

-- --------------------------------------------------------

--
-- Structure de la table `export_stat_match`
--

CREATE TABLE `export_stat_match` (
  `id_stats` int(11) NOT NULL,
  `action` varchar(100) DEFAULT NULL,
  `valeur` int(11) DEFAULT NULL,
  `id_joueur` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `fin_actions_collectives`
--

CREATE TABLE `fin_actions_collectives` (
  `id_fin_actions_collectives` int(11) NOT NULL,
  `total` int(11) DEFAULT NULL,
  `mt1` int(11) DEFAULT NULL,
  `mt2` int(11) DEFAULT NULL,
  `id_equipe` int(11) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `joueur`
--

CREATE TABLE `joueur` (
  `id_joueur` int(11) NOT NULL,
  `non_joueur` varchar(100) DEFAULT NULL,
  `prenom_joueur` varchar(100) DEFAULT NULL,
  `date_naissance` date DEFAULT NULL,
  `poste` varchar(100) DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `idp` decimal(5,2) DEFAULT NULL,
  `id_equipe` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `localisation`
--

CREATE TABLE `localisation` (
  `id_equipe` int(11) NOT NULL,
  `id_match` int(11) NOT NULL,
  `action` varchar(100) DEFAULT NULL,
  `portion_terrain` varchar(100) DEFAULT NULL,
  `temps` varchar(50) DEFAULT NULL,
  `valeur` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `match`
--

CREATE TABLE `match` (
  `id_match` int(11) NOT NULL,
  `date` date DEFAULT NULL,
  `competition` varchar(100) DEFAULT NULL,
  `locaux` varchar(100) DEFAULT NULL,
  `visiteurs` varchar(100) DEFAULT NULL,
  `score_locaux` int(11) DEFAULT NULL,
  `score_visiteurs` int(11) DEFAULT NULL,
  `stade` varchar(100) DEFAULT NULL,
  `lieu` varchar(100) DEFAULT NULL,
  `arbitre` varchar(100) DEFAULT NULL,
  `journee` varchar(255) DEFAULT NULL,
  `id_temps_effectif` int(11) DEFAULT NULL,
  `id_possession_mt_1` int(11) DEFAULT NULL,
  `id_possession_mt_2` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Déchargement des données de la table `match`
--

INSERT INTO `match` (`id_match`, `date`, `competition`, `locaux`, `visiteurs`, `score_locaux`, `score_visiteurs`, `stade`, `lieu`, `arbitre`, `journee`, `id_temps_effectif`, `id_possession_mt_1`, `id_possession_mt_2`) VALUES
(16, '2025-03-08', 'Brassage Elite Crabos', 'ASBH', 'Carcassonne', 38, 3, 'manquant', 'BEZIERS', 'manquant ', '3 ème journée', 16, 16, 16);

-- --------------------------------------------------------

--
-- Structure de la table `points`
--

CREATE TABLE `points` (
  `id_points` int(11) NOT NULL,
  `id_equipe` int(11) DEFAULT NULL,
  `id_match` int(11) DEFAULT NULL,
  `points_total` int(11) DEFAULT NULL,
  `points_positifs` int(11) DEFAULT NULL,
  `points_neutres` int(11) DEFAULT NULL,
  `points_negatifs` int(11) DEFAULT NULL,
  `actions` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Déchargement des données de la table `points`
--

INSERT INTO `points` (`id_points`, `id_equipe`, `id_match`, `points_total`, `points_positifs`, `points_neutres`, `points_negatifs`, `actions`) VALUES
(14, 1, 16, 68, 63, 0, 4, 'Ruck'),
(15, 1, 16, 3, 3, 0, 0, 'Maul'),
(16, 1, 16, 17, 13, 0, 4, 'Jeu au pied'),
(17, 1, 16, 53, 48, 0, 5, 'Plaquage'),
(18, 1, 16, 0, 0, 0, 0, 'Course'),
(19, 1, 16, 0, 0, 0, 0, 'Passe'),
(20, 1, 16, 11, 11, 0, 0, 'Franchissement'),
(21, 1, 16, 0, 0, 0, 0, 'Défi'),
(22, 1, 16, 0, 0, 0, 0, 'Soutien'),
(23, 1, 16, 4, 0, 0, 4, 'Perte de balle'),
(24, 1, 16, 4, 0, 0, 4, 'Plaquage manqué'),
(25, 1, 16, 5, 0, 0, 5, 'Faute technique'),
(26, 1, 16, 2, 2, 0, 0, 'Récupération'),
(27, 2, 16, 43, 36, 0, 5, 'Ruck'),
(28, 2, 16, 1, 0, 0, 1, 'Maul'),
(29, 2, 16, 11, 1, 9, 1, 'Jeu au pied'),
(30, 2, 16, 86, 84, 0, 2, 'Plaquage'),
(31, 2, 16, 0, 0, 0, 0, 'Course'),
(32, 2, 16, 0, 0, 0, 0, 'Passe'),
(33, 2, 16, 2, 2, 0, 0, 'Franchissement'),
(34, 2, 16, 0, 0, 0, 0, 'Défi'),
(35, 2, 16, 0, 0, 0, 0, 'Soutien'),
(36, 2, 16, 2, 0, 0, 2, 'Perte de balle'),
(37, 2, 16, 15, 0, 0, 15, 'Plaquage manqué'),
(38, 2, 16, 10, 0, 0, 10, 'Faute technique'),
(39, 2, 16, 0, 0, 0, 0, 'Récupération');

-- --------------------------------------------------------

--
-- Structure de la table `possession_mt_1`
--

CREATE TABLE `possession_mt_1` (
  `id_possession_mt_1` int(11) NOT NULL,
  `possession_mt_1_beziers` time DEFAULT NULL,
  `possession_mt_1_equipe_adverse` time DEFAULT NULL,
  `possession_mt_1_total` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Déchargement des données de la table `possession_mt_1`
--

INSERT INTO `possession_mt_1` (`id_possession_mt_1`, `possession_mt_1_beziers`, `possession_mt_1_equipe_adverse`, `possession_mt_1_total`) VALUES
(16, '00:08:07', '00:04:40', '00:12:46');

-- --------------------------------------------------------

--
-- Structure de la table `possession_mt_2`
--

CREATE TABLE `possession_mt_2` (
  `id_possession_mt_2` int(11) NOT NULL,
  `possession_mt_2_beziers` time DEFAULT NULL,
  `possession_mt_2_equipe_adverse` time DEFAULT NULL,
  `possession_mt_2_total` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Déchargement des données de la table `possession_mt_2`
--

INSERT INTO `possession_mt_2` (`id_possession_mt_2`, `possession_mt_2_beziers`, `possession_mt_2_equipe_adverse`, `possession_mt_2_total`) VALUES
(16, '00:07:10', '00:04:11', '00:11:21');

-- --------------------------------------------------------

--
-- Structure de la table `score`
--

CREATE TABLE `score` (
  `id_score` int(11) NOT NULL,
  `essais` int(11) DEFAULT NULL,
  `transformations` int(11) DEFAULT NULL,
  `drops` int(11) DEFAULT NULL,
  `drops_tentes` int(11) DEFAULT NULL,
  `penalites` int(11) DEFAULT NULL,
  `penalites_tentees` int(11) DEFAULT NULL,
  `id_equipe` int(11) DEFAULT NULL,
  `id_match` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Déchargement des données de la table `score`
--

INSERT INTO `score` (`id_score`, `essais`, `transformations`, `drops`, `drops_tentes`, `penalites`, `penalites_tentees`, `id_equipe`, `id_match`) VALUES
(17, 6, 4, 0, 0, 0, 0, 1, 16),
(18, 0, 0, 0, 0, 1, 1, 2, 16);

-- --------------------------------------------------------

--
-- Structure de la table `temps_effectif`
--

CREATE TABLE `temps_effectif` (
  `id_temps_effectif` int(11) NOT NULL,
  `temps_effectif_beziers` time DEFAULT NULL,
  `temps_effectif_equipe_adverse` time DEFAULT NULL,
  `temps_effectif_total` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Déchargement des données de la table `temps_effectif`
--

INSERT INTO `temps_effectif` (`id_temps_effectif`, `temps_effectif_beziers`, `temps_effectif_equipe_adverse`, `temps_effectif_total`) VALUES
(16, '00:15:17', '00:08:51', '00:24:07');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `courir`
--
ALTER TABLE `courir`
  ADD PRIMARY KEY (`id_joueur`,`id_match`),
  ADD KEY `id_match` (`id_match`);

--
-- Index pour la table `equipe`
--
ALTER TABLE `equipe`
  ADD PRIMARY KEY (`id_equipe`);

--
-- Index pour la table `export_stat_match`
--
ALTER TABLE `export_stat_match`
  ADD PRIMARY KEY (`id_stats`);

--
-- Index pour la table `fin_actions_collectives`
--
ALTER TABLE `fin_actions_collectives`
  ADD PRIMARY KEY (`id_fin_actions_collectives`);

--
-- Index pour la table `joueur`
--
ALTER TABLE `joueur`
  ADD PRIMARY KEY (`id_joueur`);

--
-- Index pour la table `localisation`
--
ALTER TABLE `localisation`
  ADD PRIMARY KEY (`id_equipe`,`id_match`),
  ADD KEY `id_match` (`id_match`);

--
-- Index pour la table `match`
--
ALTER TABLE `match`
  ADD PRIMARY KEY (`id_match`);

--
-- Index pour la table `points`
--
ALTER TABLE `points`
  ADD PRIMARY KEY (`id_points`),
  ADD KEY `id_equipe` (`id_equipe`),
  ADD KEY `id_match` (`id_match`);

--
-- Index pour la table `possession_mt_1`
--
ALTER TABLE `possession_mt_1`
  ADD PRIMARY KEY (`id_possession_mt_1`);

--
-- Index pour la table `possession_mt_2`
--
ALTER TABLE `possession_mt_2`
  ADD PRIMARY KEY (`id_possession_mt_2`);

--
-- Index pour la table `score`
--
ALTER TABLE `score`
  ADD PRIMARY KEY (`id_score`);

--
-- Index pour la table `temps_effectif`
--
ALTER TABLE `temps_effectif`
  ADD PRIMARY KEY (`id_temps_effectif`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `equipe`
--
ALTER TABLE `equipe`
  MODIFY `id_equipe` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `export_stat_match`
--
ALTER TABLE `export_stat_match`
  MODIFY `id_stats` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `fin_actions_collectives`
--
ALTER TABLE `fin_actions_collectives`
  MODIFY `id_fin_actions_collectives` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `joueur`
--
ALTER TABLE `joueur`
  MODIFY `id_joueur` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `match`
--
ALTER TABLE `match`
  MODIFY `id_match` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT pour la table `points`
--
ALTER TABLE `points`
  MODIFY `id_points` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT pour la table `possession_mt_1`
--
ALTER TABLE `possession_mt_1`
  MODIFY `id_possession_mt_1` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT pour la table `possession_mt_2`
--
ALTER TABLE `possession_mt_2`
  MODIFY `id_possession_mt_2` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT pour la table `score`
--
ALTER TABLE `score`
  MODIFY `id_score` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT pour la table `temps_effectif`
--
ALTER TABLE `temps_effectif`
  MODIFY `id_temps_effectif` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `courir`
--
ALTER TABLE `courir`
  ADD CONSTRAINT `courir_ibfk_1` FOREIGN KEY (`id_joueur`) REFERENCES `joueur` (`id_joueur`),
  ADD CONSTRAINT `courir_ibfk_2` FOREIGN KEY (`id_match`) REFERENCES `match` (`id_match`);

--
-- Contraintes pour la table `localisation`
--
ALTER TABLE `localisation`
  ADD CONSTRAINT `localisation_ibfk_1` FOREIGN KEY (`id_equipe`) REFERENCES `equipe` (`id_equipe`),
  ADD CONSTRAINT `localisation_ibfk_2` FOREIGN KEY (`id_match`) REFERENCES `match` (`id_match`);

--
-- Contraintes pour la table `points`
--
ALTER TABLE `points`
  ADD CONSTRAINT `points_ibfk_1` FOREIGN KEY (`id_equipe`) REFERENCES `equipe` (`id_equipe`),
  ADD CONSTRAINT `points_ibfk_2` FOREIGN KEY (`id_match`) REFERENCES `match` (`id_match`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
