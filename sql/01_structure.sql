-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : jeu. 19 nov. 2026 à 21:38
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `ecf_studi`
--

-- --------------------------------------------------------

--
-- Structure de la table `accessoires`
--

CREATE TABLE `accessoires` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `categorie` enum('vetement','armure','arme','autre') NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `avis`
--

CREATE TABLE `avis` (
  `id` int(11) NOT NULL,
  `personnage_id` int(11) NOT NULL,
  `utilisateur_id` int(11) NOT NULL,
  `note` tinyint(4) NOT NULL,
  `commentaire` text NOT NULL,
  `statut` enum('en_attente','approuve','refuse') NOT NULL DEFAULT 'en_attente',
  `date_creation` timestamp NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Structure de la table `historique_personnages`
--

CREATE TABLE `historique_personnages` (
  `id` int(11) NOT NULL,
  `personnage_id` int(11) NOT NULL,
  `personnage_nom` varchar(100) NOT NULL,
  `utilisateur_id` int(11) NOT NULL,
  `type_modification` varchar(50) NOT NULL,
  `anciennes_valeurs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`anciennes_valeurs`)),
  `nouvelles_valeurs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`nouvelles_valeurs`)),
  `date_modification` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `personnages`
--

CREATE TABLE `personnages` (
  `id` int(11) NOT NULL,
  `utilisateur_id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `genre` varchar(50) NOT NULL,
  `yeux_forme` varchar(50) DEFAULT NULL,
  `nez_forme` varchar(50) DEFAULT NULL,
  `bouche_forme` varchar(50) DEFAULT NULL,
  `couleur_peau` varchar(50) DEFAULT NULL,
  `couleur_cheveux` varchar(50) DEFAULT NULL,
  `couleur_yeux` varchar(50) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `statut` enum('en_attente','approuve') NOT NULL DEFAULT 'en_attente',
  `partage` tinyint(1) DEFAULT 0,
  `date_creation` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `personnage_accessoires`
--

CREATE TABLE `personnage_accessoires` (
  `personnage_id` int(11) NOT NULL,
  `accessoire_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `refus_personnages`
--

CREATE TABLE `refus_personnages` (
  `id` int(11) NOT NULL,
  `personnage_nom` varchar(100) NOT NULL,
  `utilisateur_id` int(11) DEFAULT NULL,
  `motif` text NOT NULL,
  `moderateur_id` int(11) DEFAULT NULL,
  `date_refus` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `reinitialisations_mdp`
--

CREATE TABLE `reinitialisations_mdp` (
  `id` int(11) NOT NULL,
  `utilisateur_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expiration` datetime NOT NULL,
  `date_creation` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tentatives_reinitialisation_mdp`
--

CREATE TABLE `tentatives_reinitialisation_mdp` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `email_hash` char(64) NOT NULL,
  `ip_hash` char(64) NOT NULL,
  `date_tentative` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `pseudo` varchar(50) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `role` enum('utilisateur','employe','admin') NOT NULL DEFAULT 'utilisateur',
  `statut` enum('actif','suspendu') NOT NULL DEFAULT 'actif',
  `changement_mdp_requis` tinyint(1) DEFAULT 0,
  `date_creation` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `accessoires`
--
ALTER TABLE `accessoires`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `avis`
--
ALTER TABLE `avis`
  ADD PRIMARY KEY (`id`),
  ADD KEY `personnage_id` (`personnage_id`),
  ADD KEY `utilisateur_id` (`utilisateur_id`);

--
-- Index pour la table `historique_personnages`
--
ALTER TABLE `historique_personnages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_personnage` (`personnage_id`),
  ADD KEY `idx_utilisateur` (`utilisateur_id`),
  ADD KEY `idx_date` (`date_modification`);

--
-- Index pour la table `personnages`
--
ALTER TABLE `personnages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nom` (`nom`),
  ADD KEY `utilisateur_id` (`utilisateur_id`);

--
-- Index pour la table `personnage_accessoires`
--
ALTER TABLE `personnage_accessoires`
  ADD PRIMARY KEY (`personnage_id`,`accessoire_id`),
  ADD KEY `accessoire_id` (`accessoire_id`);

--
-- Index pour la table `refus_personnages`
--
ALTER TABLE `refus_personnages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `utilisateur_id` (`utilisateur_id`),
  ADD KEY `moderateur_id` (`moderateur_id`);

--
-- Index pour la table `reinitialisations_mdp`
--
ALTER TABLE `reinitialisations_mdp`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `utilisateur_id` (`utilisateur_id`);

--
-- Index pour la table `tentatives_reinitialisation_mdp`
--
ALTER TABLE `tentatives_reinitialisation_mdp`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_date` (`email_hash`,`date_tentative`),
  ADD KEY `idx_ip_date` (`ip_hash`,`date_tentative`);

--
-- Index pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `pseudo` (`pseudo`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `accessoires`
--
ALTER TABLE `accessoires`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `avis`
--
ALTER TABLE `avis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `historique_personnages`
--
ALTER TABLE `historique_personnages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `personnages`
--
ALTER TABLE `personnages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `refus_personnages`
--
ALTER TABLE `refus_personnages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `reinitialisations_mdp`
--
ALTER TABLE `reinitialisations_mdp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `tentatives_reinitialisation_mdp`
--
ALTER TABLE `tentatives_reinitialisation_mdp`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `avis`
--
ALTER TABLE `avis`
  ADD CONSTRAINT `avis_ibfk_1` FOREIGN KEY (`personnage_id`) REFERENCES `personnages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `avis_ibfk_2` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `personnages`
--
ALTER TABLE `personnages`
  ADD CONSTRAINT `personnages_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `personnage_accessoires`
--
ALTER TABLE `personnage_accessoires`
  ADD CONSTRAINT `personnage_accessoires_ibfk_1` FOREIGN KEY (`personnage_id`) REFERENCES `personnages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `personnage_accessoires_ibfk_2` FOREIGN KEY (`accessoire_id`) REFERENCES `accessoires` (`id`);

--
-- Contraintes pour la table `refus_personnages`
--
ALTER TABLE `refus_personnages`
  ADD CONSTRAINT `refus_personnages_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `refus_personnages_ibfk_2` FOREIGN KEY (`moderateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `reinitialisations_mdp`
--
ALTER TABLE `reinitialisations_mdp`
  ADD CONSTRAINT `reinitialisations_mdp_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
