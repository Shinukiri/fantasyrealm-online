-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : jeu. 19 nov. 2026 à 21:42
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

--
-- Déchargement des données de la table `accessoires`
--

INSERT INTO `accessoires` (`id`, `nom`, `categorie`, `image`, `actif`) VALUES
(1, 'Épée légendaire', 'arme', NULL, 1),
(2, 'Arc elfique', 'arme', NULL, 1),
(3, 'Bâton de magie', 'arme', NULL, 1),
(4, 'Armure de chevalier', 'armure', NULL, 1),
(5, 'Armure draconique', 'armure', NULL, 0),
(6, 'Robe de mage', 'vetement', NULL, 1),
(7, 'Cape des ombres', 'vetement', NULL, 1),
(8, 'Casque royal', 'armure', NULL, 1),
(10, 'Durandal', 'arme', NULL, 1);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
