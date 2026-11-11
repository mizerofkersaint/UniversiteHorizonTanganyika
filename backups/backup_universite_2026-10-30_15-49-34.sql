-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: universite
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `agents`
--

DROP TABLE IF EXISTS `agents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `agents` (
  `id_agent` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `date_embauche` date DEFAULT NULL,
  PRIMARY KEY (`id_agent`),
  KEY `id_user` (`id_user`),
  CONSTRAINT `agents_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `agents`
--

LOCK TABLES `agents` WRITE;
/*!40000 ALTER TABLE `agents` DISABLE KEYS */;
INSERT INTO `agents` VALUES (6,19,'2026-10-29');
/*!40000 ALTER TABLE `agents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `annonces_professeurs`
--

DROP TABLE IF EXISTS `annonces_professeurs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `annonces_professeurs` (
  `id_annonce` int(11) NOT NULL AUTO_INCREMENT,
  `id_prof` int(11) NOT NULL,
  `id_cours` int(11) DEFAULT NULL,
  `id_classe` int(11) DEFAULT NULL,
  `titre` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `date_publication` datetime DEFAULT current_timestamp(),
  `statut` enum('active','archivee') DEFAULT 'active',
  PRIMARY KEY (`id_annonce`),
  KEY `id_prof` (`id_prof`),
  KEY `id_cours` (`id_cours`),
  KEY `id_classe` (`id_classe`),
  CONSTRAINT `annonces_professeurs_ibfk_1` FOREIGN KEY (`id_prof`) REFERENCES `professeurs` (`id_prof`) ON DELETE CASCADE,
  CONSTRAINT `annonces_professeurs_ibfk_2` FOREIGN KEY (`id_cours`) REFERENCES `cours` (`id_cours`) ON DELETE CASCADE,
  CONSTRAINT `annonces_professeurs_ibfk_3` FOREIGN KEY (`id_classe`) REFERENCES `classes` (`id_classe`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `annonces_professeurs`
--

LOCK TABLES `annonces_professeurs` WRITE;
/*!40000 ALTER TABLE `annonces_professeurs` DISABLE KEYS */;
/*!40000 ALTER TABLE `annonces_professeurs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `archivage`
--

DROP TABLE IF EXISTS `archivage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `archivage` (
  `id_archivage` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) DEFAULT NULL,
  `nom_utilisateur` varchar(100) DEFAULT NULL,
  `prenom_utilisateur` varchar(100) DEFAULT NULL,
  `role_utilisateur` varchar(50) DEFAULT NULL,
  `action` varchar(20) NOT NULL,
  `table_concernee` varchar(50) NOT NULL,
  `id_concerned` int(11) DEFAULT NULL,
  `ancienne_valeur` text DEFAULT NULL,
  `nouvelle_valeur` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `adresse_ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `date_action` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_archivage`),
  KEY `idx_user` (`id_user`),
  KEY `idx_action` (`action`),
  KEY `idx_table` (`table_concernee`),
  KEY `idx_date` (`date_action`),
  CONSTRAINT `fk_archivage_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `archivage`
--

LOCK TABLES `archivage` WRITE;
/*!40000 ALTER TABLE `archivage` DISABLE KEYS */;
INSERT INTO `archivage` VALUES (1,NULL,NULL,NULL,NULL,'INSERT','departements',999999,NULL,'{\"id_dept\":999999,\"nom_dept\":\"TEST\"}','Test archivage','127.0.0.1','Test','2026-10-06 16:36:18'),(2,NULL,NULL,NULL,NULL,'INSERT','departements',17,NULL,'{\"id_dept\":\"17\",\"nom_dept\":\"gh\",\"faculte\":\"Faculté de Droit\",\"description\":\"khmium\"}','Création du département','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0','2026-10-06 18:26:06'),(3,NULL,NULL,NULL,NULL,'UPDATE','departements',13,'{\"id_dept\":\"13\",\"nom_dept\":\"FSI\",\"faculte\":null,\"description\":\"Faculte Science et Ingeniers\"}','{\"id_dept\":\"13\",\"nom_dept\":\"FSI\",\"faculte\":null,\"description\":\"Faculte Science et Ingeniers s\"}','Modification du département','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0','2026-10-10 15:16:53'),(4,NULL,NULL,NULL,NULL,'DELETE','departements',17,'{\"id_dept\":\"17\",\"nom_dept\":\"gh\",\"faculte\":\"Faculté de Droit\",\"description\":\"khmium\"}',NULL,'Suppression du département','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0','2026-10-10 15:17:03'),(5,NULL,NULL,NULL,NULL,'INSERT','departements',18,NULL,'{\"id_dept\":\"18\",\"nom_dept\":\"droit cr\",\"faculte\":\"Faculté de Droit\",\"description\":\"eedf\"}','Création du département','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0','2026-10-12 17:25:29'),(6,NULL,NULL,NULL,NULL,'INSERT','classes',13,NULL,'{\"id_classe\":\"13\",\"niveau\":\"BAC V\",\"id_dept\":\"18\"}','Création de la classe','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0','2026-10-12 17:25:59'),(7,NULL,NULL,NULL,NULL,'UPDATE','classes',9,'{\"id_classe\":\"9\",\"niveau\":\"BAC I\",\"id_dept\":\"6\"}','{\"id_classe\":\"9\",\"niveau\":\"BAC II\",\"id_dept\":\"6\"}','Modification de la classe','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0','2026-10-12 17:46:36'),(8,NULL,NULL,NULL,NULL,'INSERT','users',7,NULL,'{\"id_user\":\"7\",\"nom\":\"bn\",\"prenom\":\"ef\",\"sexe\":\"Masculin\",\"telephone\":\"+25700000000\",\"email\":\"test.user@example.com\",\"email_auth\":\"ebn.t.user@univ.local\",\"username\":\"t.user\",\"role\":\"Lecteur\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Oui\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":null,\"derniere_connexion\":null,\"date_creation\":\"2026-10-13 14:58:39\"}','Création d\'un utilisateur : t.user','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0','2026-10-13 14:58:39'),(9,NULL,NULL,NULL,NULL,'INSERT','backup',NULL,NULL,'{\"fichier\":\"backup_universite_2026-10-13_15-33-27.sql\",\"taille\":131746,\"date\":\"2026-10-13 15:33:28\",\"base\":\"universite\"}','Création d\'une sauvegarde : backup_universite_2026-10-13_15-33-27.sql','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0','2026-10-13 15:33:28'),(10,NULL,NULL,NULL,NULL,'INSERT','backup',NULL,NULL,'{\"fichier\":\"backup_universite_2026-10-13_15-37-56.sql\",\"taille\":132166,\"date\":\"2026-10-13 15:37:57\",\"base\":\"universite\"}','Création d\'une sauvegarde : backup_universite_2026-10-13_15-37-56.sql','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0','2026-10-13 15:37:57'),(11,NULL,NULL,NULL,NULL,'INSERT','users',8,NULL,'{\"id_user\":\"8\",\"nom\":\"Mizero\",\"prenom\":\"Karel\",\"sexe\":\"Masculin\",\"telephone\":\"+25764346117\",\"email\":\"mizerokarel@gmail.com\",\"email_auth\":\"kmizero.mizerok@univ.local\",\"username\":\"Mizerok\",\"role\":\"Administrateur\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Oui\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":null,\"derniere_connexion\":null,\"date_creation\":\"2026-10-20 17:20:17\"}','Création d\'un utilisateur : Mizerok','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-20 17:20:17'),(12,NULL,NULL,NULL,NULL,'INSERT','backup',NULL,NULL,'{\"fichier\":\"backup_universite_2026-10-20_17-21-03.sql\",\"taille\":144060,\"date\":\"2026-10-20 17:21:04\",\"base\":\"universite\"}','Création d\'une sauvegarde : backup_universite_2026-10-20_17-21-03.sql','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-20 17:21:04'),(13,8,'Mizero','Karel','Administrateur','INSERT','users',9,NULL,'{\"id_user\":\"9\",\"nom\":\"Niyomwungere\",\"prenom\":\"Olyne\",\"sexe\":\"Feminin\",\"telephone\":\"+25764346117\",\"email\":\"niyomwungereolyne@gmail.com\",\"email_auth\":\"oniyomwungere.niyomwungere@univ.local\",\"username\":\"Niyomwungere\",\"role\":\"Doyen\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Oui\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":null,\"derniere_connexion\":null,\"date_creation\":\"2026-10-20 17:48:39\"}','Création d\'un utilisateur : Niyomwungere','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-20 17:48:39'),(14,8,'Mizero','Karel','Administrateur','INSERT','backup',NULL,NULL,'{\"fichier\":\"backup_universite_2026-10-23_15-26-25.sql\",\"taille\":146750,\"date\":\"2026-10-23 15:26:26\",\"base\":\"universite\"}','Création d\'une sauvegarde : backup_universite_2026-10-23_15-26-25.sql','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-23 15:26:26'),(15,9,'Niyomwungere','Olyne','Doyen','UPDATE','users',9,'{\"id_user\":\"9\",\"nom\":\"Niyomwungere\",\"prenom\":\"Olyne\",\"sexe\":\"Feminin\",\"telephone\":\"+25764346117\",\"email\":\"mizerofk@gmail.com\",\"email_auth\":\"oniyomwungere.niyomwungere@univ.local\",\"username\":\"Niyomwungere\",\"role\":\"Doyen\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Oui\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":null,\"derniere_connexion\":\"2026-10-24 17:22:33\",\"date_creation\":\"2026-10-20 17:48:39\",\"derniere_activite\":null}','{\"id_user\":\"9\",\"nom\":\"Niyomwungere\",\"prenom\":\"Olyne\",\"sexe\":\"Feminin\",\"telephone\":\"+25764346117\",\"email\":\"mizerofk@gmail.com\",\"email_auth\":\"oniyomwungere.niyomwungere@univ.local\",\"username\":\"Niyomwungere\",\"role\":\"Doyen\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Non\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":\"2026-10-24 17:24:16\",\"derniere_connexion\":\"2026-10-24 17:22:33\",\"date_creation\":\"2026-10-20 17:48:39\",\"derniere_activite\":null}','Changement de mot de passe','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0','2026-10-24 17:24:16'),(16,8,'Mizero','Karel','Administrateur','UPDATE','users',8,'{\"id_user\":\"8\",\"nom\":\"Mizero\",\"prenom\":\"Karel\",\"sexe\":\"Masculin\",\"telephone\":\"+25764346117\",\"email\":\"mizerofk@gmail.com\",\"email_auth\":\"kmizero.mizerok@univ.local\",\"username\":\"Mizerok\",\"role\":\"Administrateur\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Oui\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":null,\"derniere_connexion\":\"2026-10-26 12:33:08\",\"date_creation\":\"2026-10-20 17:20:17\",\"derniere_activite\":null}','{\"id_user\":\"8\",\"nom\":\"Mizero\",\"prenom\":\"Karel\",\"sexe\":\"Masculin\",\"telephone\":\"+25764346117\",\"email\":\"mizerofk@gmail.com\",\"email_auth\":\"kmizero.mizerok@univ.local\",\"username\":\"Mizerok\",\"role\":\"Administrateur\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Non\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":\"2026-10-26 12:33:42\",\"derniere_connexion\":\"2026-10-26 12:33:08\",\"date_creation\":\"2026-10-20 17:20:17\",\"derniere_activite\":null}','Changement de mot de passe','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-10-26 12:33:42'),(17,8,'Mizero','Karel','Administrateur','INSERT','users',12,NULL,'{\"id_user\":\"12\",\"nom\":\"BIGIRIMANA\",\"prenom\":\"Marc\",\"sexe\":\"Masculin\",\"telephone\":\"+25764346117\",\"email\":\"mizero100@gmail.com\",\"email_auth\":\"mbigirimana.bigmarc@univ.local\",\"username\":\"BIGMARC\",\"role\":\"Professeur\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Oui\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":null,\"derniere_connexion\":null,\"date_creation\":\"2026-10-26 13:54:04\",\"derniere_activite\":null}','Création d\'un utilisateur : BIGMARC','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-10-26 13:54:04'),(18,12,'BIGIRIMANA','Marc','Professeur','UPDATE','users',12,'{\"id_user\":\"12\",\"nom\":\"BIGIRIMANA\",\"prenom\":\"Marc\",\"sexe\":\"Masculin\",\"telephone\":\"+25764346117\",\"email\":\"mizero100@gmail.com\",\"email_auth\":\"mbigirimana.bigmarc@univ.local\",\"username\":\"BIGMARC\",\"role\":\"Professeur\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Oui\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":null,\"derniere_connexion\":\"2026-10-26 14:04:47\",\"date_creation\":\"2026-10-26 13:54:04\",\"derniere_activite\":null}','{\"id_user\":\"12\",\"nom\":\"BIGIRIMANA\",\"prenom\":\"Marc\",\"sexe\":\"Masculin\",\"telephone\":\"+25764346117\",\"email\":\"mizero100@gmail.com\",\"email_auth\":\"mbigirimana.bigmarc@univ.local\",\"username\":\"BIGMARC\",\"role\":\"Professeur\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Non\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":\"2026-10-26 14:05:16\",\"derniere_connexion\":\"2026-10-26 14:04:47\",\"date_creation\":\"2026-10-26 13:54:04\",\"derniere_activite\":null}','Changement de mot de passe','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-10-26 14:05:16'),(19,5,'mizero','kersaint','Etudiant','UPDATE','users',5,'{\"id_user\":\"5\",\"nom\":\"mizero\",\"prenom\":\"kersaint\",\"sexe\":\"Masculin\",\"telephone\":\"+25766012083\",\"email\":\"mizero100@gmail.com\",\"email_auth\":\"kmizero.k.mizero@univ.local\",\"username\":\"k.mizero\",\"role\":\"Etudiant\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Oui\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":null,\"derniere_connexion\":\"2026-10-26 14:18:22\",\"date_creation\":\"2026-07-07 14:53:16\",\"derniere_activite\":null}','{\"id_user\":\"5\",\"nom\":\"mizero\",\"prenom\":\"kersaint\",\"sexe\":\"Masculin\",\"telephone\":\"+25766012083\",\"email\":\"mizero100@gmail.com\",\"email_auth\":\"kmizero.k.mizero@univ.local\",\"username\":\"k.mizero\",\"role\":\"Etudiant\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Non\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":\"2026-10-26 14:18:46\",\"derniere_connexion\":\"2026-10-26 14:18:22\",\"date_creation\":\"2026-07-07 14:53:16\",\"derniere_activite\":null}','Changement de mot de passe','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-10-26 14:18:46'),(20,3,'mizero','francis','Etudiant','UPDATE','users',3,'{\"id_user\":\"3\",\"nom\":\"mizero\",\"prenom\":\"francis\",\"sexe\":\"Masculin\",\"telephone\":\"+25764346117\",\"email\":\"mizero100@gmail.com\",\"email_auth\":\"fmizero.f.mizero@univ.local\",\"username\":\"f.mizero\",\"role\":\"Etudiant\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Oui\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":null,\"derniere_connexion\":\"2026-10-26 14:25:27\",\"date_creation\":\"2026-07-07 14:14:06\",\"derniere_activite\":null}','{\"id_user\":\"3\",\"nom\":\"mizero\",\"prenom\":\"francis\",\"sexe\":\"Masculin\",\"telephone\":\"+25764346117\",\"email\":\"mizero100@gmail.com\",\"email_auth\":\"fmizero.f.mizero@univ.local\",\"username\":\"f.mizero\",\"role\":\"Etudiant\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Non\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":\"2026-10-26 14:25:49\",\"derniere_connexion\":\"2026-10-26 14:25:27\",\"date_creation\":\"2026-07-07 14:14:06\",\"derniere_activite\":null}','Changement de mot de passe','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-10-26 14:25:49'),(21,3,'mizero','francis','Etudiant','SELECT','supports_cours',1,NULL,NULL,'Téléchargement support : Introduction','::1',NULL,'2026-10-26 15:00:17'),(22,8,'Mizero','Karel','Administrateur','INSERT','backup',NULL,NULL,'{\"fichier\":\"backup_universite_2026-10-28_14-51-57.sql\",\"taille\":167973,\"date\":\"2026-10-28 14:51:58\",\"base\":\"universite\"}','Création d\'une sauvegarde : backup_universite_2026-10-28_14-51-57.sql','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-10-28 14:51:58'),(23,8,'Mizero','Karel','Administrateur','UPDATE','departements',18,'{\"id_dept\":\"18\",\"nom_dept\":\"droit cr\",\"faculte\":\"Faculté de Droit\",\"description\":\"eedf\"}','{\"id_dept\":\"18\",\"nom_dept\":\"Droit International\",\"faculte\":\"Faculté de Droit\",\"description\":\"Faculte de Droit\"}','Modification du département','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-10-29 13:34:43'),(24,8,'Mizero','Karel','Administrateur','UPDATE','departements',18,'{\"id_dept\":\"18\",\"nom_dept\":\"Droit International\",\"faculte\":\"Faculté de Droit\",\"description\":\"Faculte de Droit\"}','{\"id_dept\":\"18\",\"nom_dept\":\"Droit International\",\"faculte\":\"Faculté de Droit\",\"description\":\"Filière de la faculté\"}','Modification du département','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-10-29 13:35:16'),(25,8,'Mizero','Karel','Administrateur','INSERT','users',14,NULL,'{\"id_user\":\"14\",\"nom\":\"KEZA\",\"prenom\":\"Micky\",\"sexe\":\"Feminin\",\"telephone\":\"+25764346117\",\"email\":\"mizero@gmail.com\",\"email_auth\":\"mkeza@ins.univ.local\",\"username\":\"Micky\",\"role\":\"Agent\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Oui\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":null,\"derniere_connexion\":null,\"date_creation\":\"2026-10-29 13:55:34\",\"derniere_activite\":null}','Création d\'un utilisateur : Micky','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-10-29 13:55:34'),(26,8,'Mizero','Karel','Administrateur','INSERT','users',15,NULL,'{\"id_user\":\"15\",\"nom\":\"KEZA\",\"prenom\":\"Micky\",\"sexe\":\"Feminin\",\"telephone\":\"+25764346117\",\"email\":\"mizero456@gmail.com\",\"email_auth\":\"mkeza1@ins.univ.local\",\"username\":\"Mickyk\",\"role\":\"Agent\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Oui\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":null,\"derniere_connexion\":null,\"date_creation\":\"2026-10-29 13:58:22\",\"derniere_activite\":null}','Création d\'un utilisateur : Mickyk','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-10-29 13:58:22'),(27,8,'Mizero','Karel','Administrateur','INSERT','users',16,NULL,'{\"id_user\":\"16\",\"nom\":\"KEZA\",\"prenom\":\"Micky\",\"sexe\":\"Feminin\",\"telephone\":\"+25764346117\",\"email\":\"micky@gmail.com\",\"email_auth\":\"mkeza2@ins.univ.local\",\"username\":\"Kmicky\",\"role\":\"Agent\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Oui\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":null,\"derniere_connexion\":null,\"date_creation\":\"2026-10-29 13:59:12\",\"derniere_activite\":null}','Création d\'un utilisateur : Kmicky','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-10-29 13:59:12'),(28,8,'Mizero','Karel','Administrateur','INSERT','users',17,NULL,'{\"id_user\":\"17\",\"nom\":\"KEZA\",\"prenom\":\"Micky\",\"sexe\":\"Feminin\",\"telephone\":\"+25764346117\",\"email\":\"kezamicky@gmail.com\",\"email_auth\":\"mkeza3@ins.univ.local\",\"username\":\"Kmickyagent\",\"role\":\"Agent\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Oui\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":null,\"derniere_connexion\":null,\"date_creation\":\"2026-10-29 14:00:19\",\"derniere_activite\":null}','Création d\'un utilisateur : Kmickyagent','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-10-29 14:00:19'),(29,8,'Mizero','Karel','Administrateur','INSERT','users',18,NULL,'{\"id_user\":\"18\",\"nom\":\"KEZA\",\"prenom\":\"Micky\",\"sexe\":\"Feminin\",\"telephone\":\"+25764346117\",\"email\":\"kezamicky123@gmail.com\",\"email_auth\":\"mkeza4@ins.univ.local\",\"username\":\"KEZAMICKY\",\"role\":\"Agent\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Oui\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":null,\"derniere_connexion\":null,\"date_creation\":\"2026-10-29 14:01:15\",\"derniere_activite\":null}','Création d\'un utilisateur : KEZAMICKY','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-10-29 14:01:15'),(30,NULL,'KEZA','Micky','Administrateur','UPDATE','users',14,'{\"id_user\":\"14\",\"nom\":\"KEZA\",\"prenom\":\"Micky\",\"sexe\":\"Feminin\",\"telephone\":\"+25764346117\",\"email\":\"mizero100@gmail.com\",\"email_auth\":\"mkeza@ins.univ.local\",\"username\":\"Micky\",\"role\":\"Administrateur\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Oui\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":null,\"derniere_connexion\":\"2026-10-29 14:25:48\",\"date_creation\":\"2026-10-29 13:55:34\",\"derniere_activite\":null}','{\"id_user\":\"14\",\"nom\":\"KEZA\",\"prenom\":\"Micky\",\"sexe\":\"Feminin\",\"telephone\":\"+25764346117\",\"email\":\"mizero100@gmail.com\",\"email_auth\":\"mkeza@ins.univ.local\",\"username\":\"Micky\",\"role\":\"Administrateur\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Non\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":\"2026-10-29 14:30:20\",\"derniere_connexion\":\"2026-10-29 14:25:48\",\"date_creation\":\"2026-10-29 13:55:34\",\"derniere_activite\":null}','Changement de mot de passe','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-10-29 14:30:20'),(31,NULL,'KEZA','Micky','Administrateur','INSERT','users',19,NULL,'{\"id_user\":\"19\",\"nom\":\"KEZA\",\"prenom\":\"Micky\",\"sexe\":\"Feminin\",\"telephone\":\"+25764346117\",\"email\":\"kezamicky123@gmail.com\",\"email_auth\":\"mkeza1@ins.univ.local\",\"username\":\"KEZAMICKY\",\"role\":\"Agent\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Oui\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":null,\"derniere_connexion\":null,\"date_creation\":\"2026-10-29 14:31:12\",\"derniere_activite\":null}','Création d\'un utilisateur : KEZAMICKY','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-10-29 14:31:12'),(32,19,'KEZA','Micky','Agent','UPDATE','users',19,'{\"id_user\":\"19\",\"nom\":\"KEZA\",\"prenom\":\"Micky\",\"sexe\":\"Feminin\",\"telephone\":\"+25764346117\",\"email\":\"mizero100@gmail.com\",\"email_auth\":\"mkeza1@ins.univ.local\",\"username\":\"KEZAMICKY\",\"role\":\"Agent\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Oui\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":null,\"derniere_connexion\":\"2026-10-29 14:42:28\",\"date_creation\":\"2026-10-29 14:31:12\",\"derniere_activite\":null}','{\"id_user\":\"19\",\"nom\":\"KEZA\",\"prenom\":\"Micky\",\"sexe\":\"Feminin\",\"telephone\":\"+25764346117\",\"email\":\"mizero100@gmail.com\",\"email_auth\":\"mkeza1@ins.univ.local\",\"username\":\"KEZAMICKY\",\"role\":\"Agent\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Non\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":\"2026-10-29 14:42:50\",\"derniere_connexion\":\"2026-10-29 14:42:28\",\"date_creation\":\"2026-10-29 14:31:12\",\"derniere_activite\":null}','Changement de mot de passe','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-10-29 14:42:50'),(33,8,'Mizero','Karel','Administrateur','INSERT','users',20,NULL,'{\"id_user\":\"20\",\"nom\":\"MANARIYO\",\"prenom\":\"GUY Ernell\",\"sexe\":\"Masculin\",\"telephone\":\"+25764346117\",\"email\":\"guyernel@gmail.com\",\"email_auth\":\"gmanariyo@lec.univ.local\",\"username\":\"MANAGUY\",\"role\":\"Lecteur\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Oui\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":null,\"derniere_connexion\":null,\"date_creation\":\"2026-10-29 16:11:23\",\"derniere_activite\":null}','Création d\'un utilisateur : MANAGUY','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-10-29 16:11:23'),(34,8,'Mizero','Karel','Administrateur','INSERT','users',21,NULL,'{\"id_user\":\"21\",\"nom\":\"MIZERO\",\"prenom\":\"Alicia\",\"sexe\":\"Feminin\",\"telephone\":\"+25764346117\",\"email\":\"mizeroalicia@gmail.com\",\"email_auth\":\"amizero@cmp.univ.local\",\"username\":\"mizalicia\",\"role\":\"Comptable\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Oui\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":null,\"derniere_connexion\":null,\"date_creation\":\"2026-10-29 16:14:06\",\"derniere_activite\":null}','Création d\'un utilisateur : mizalicia','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-10-29 16:14:06'),(35,20,'MANARIYO','GUY Ernell','Lecteur','UPDATE','users',20,'{\"id_user\":\"20\",\"nom\":\"MANARIYO\",\"prenom\":\"GUY Ernell\",\"sexe\":\"Masculin\",\"telephone\":\"+25764346117\",\"email\":\"mizero100@gmail.com\",\"email_auth\":\"gmanariyo@lec.univ.local\",\"username\":\"MANAGUY\",\"role\":\"Lecteur\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Oui\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":null,\"derniere_connexion\":\"2026-10-29 16:20:17\",\"date_creation\":\"2026-10-29 16:11:23\",\"derniere_activite\":null}','{\"id_user\":\"20\",\"nom\":\"MANARIYO\",\"prenom\":\"GUY Ernell\",\"sexe\":\"Masculin\",\"telephone\":\"+25764346117\",\"email\":\"mizero100@gmail.com\",\"email_auth\":\"gmanariyo@lec.univ.local\",\"username\":\"MANAGUY\",\"role\":\"Lecteur\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Non\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":\"2026-10-29 16:21:19\",\"derniere_connexion\":\"2026-10-29 16:20:17\",\"date_creation\":\"2026-10-29 16:11:23\",\"derniere_activite\":null}','Changement de mot de passe','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-10-29 16:21:19'),(36,8,'Mizero','Karel','Administrateur','INSERT','backup',NULL,NULL,'{\"fichier\":\"backup_universite_2026-10-30_12-49-31.sql\",\"taille\":188557,\"date\":\"2026-10-30 12:49:32\",\"base\":\"universite\"}','Création d\'une sauvegarde : backup_universite_2026-10-30_12-49-31.sql','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-10-30 12:49:32'),(37,21,'MIZERO','Alicia','Comptable','UPDATE','users',21,'{\"id_user\":\"21\",\"nom\":\"MIZERO\",\"prenom\":\"Alicia\",\"sexe\":\"Feminin\",\"telephone\":\"+25764346117\",\"email\":\"mizero100@gmail.com\",\"email_auth\":\"amizero@cmp.univ.local\",\"username\":\"mizalicia\",\"role\":\"Comptable\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Oui\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":null,\"derniere_connexion\":\"2026-10-30 14:39:56\",\"date_creation\":\"2026-10-29 16:14:06\",\"derniere_activite\":null}','{\"id_user\":\"21\",\"nom\":\"MIZERO\",\"prenom\":\"Alicia\",\"sexe\":\"Feminin\",\"telephone\":\"+25764346117\",\"email\":\"mizero100@gmail.com\",\"email_auth\":\"amizero@cmp.univ.local\",\"username\":\"mizalicia\",\"role\":\"Comptable\",\"statut\":\"Actif\",\"photo\":\"default.png\",\"premiere_connexion\":\"Non\",\"tentative_connexion\":\"0\",\"date_blocage\":null,\"date_modification_mdp\":\"2026-10-30 14:40:16\",\"derniere_connexion\":\"2026-10-30 14:39:56\",\"date_creation\":\"2026-10-29 16:14:06\",\"derniere_activite\":null}','Changement de mot de passe','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-10-30 14:40:16');
/*!40000 ALTER TABLE `archivage` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `archivage_backup_step2`
--

DROP TABLE IF EXISTS `archivage_backup_step2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `archivage_backup_step2` (
  `id_archivage` int(11) NOT NULL DEFAULT 0,
  `id_user` int(11) DEFAULT NULL,
  `nom_utilisateur` varchar(100) DEFAULT NULL,
  `prenom_utilisateur` varchar(100) DEFAULT NULL,
  `role_utilisateur` varchar(50) DEFAULT NULL,
  `action` varchar(20) NOT NULL,
  `table_concernee` varchar(50) NOT NULL,
  `id_concerned` int(11) DEFAULT NULL,
  `ancienne_valeur` text DEFAULT NULL,
  `nouvelle_valeur` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `adresse_ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `date_action` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `archivage_backup_step2`
--

LOCK TABLES `archivage_backup_step2` WRITE;
/*!40000 ALTER TABLE `archivage_backup_step2` DISABLE KEYS */;
/*!40000 ALTER TABLE `archivage_backup_step2` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_financier`
--

DROP TABLE IF EXISTS `audit_financier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_financier` (
  `id_audit` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `table_concernee` varchar(50) NOT NULL,
  `id_concerned` int(11) DEFAULT NULL,
  `ancienne_valeur` text DEFAULT NULL,
  `nouvelle_valeur` text DEFAULT NULL,
  `adresse_ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `date_action` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_audit`),
  KEY `id_user` (`id_user`),
  CONSTRAINT `audit_financier_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_financier`
--

LOCK TABLES `audit_financier` WRITE;
/*!40000 ALTER TABLE `audit_financier` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_financier` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_messages`
--

DROP TABLE IF EXISTS `chat_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_messages` (
  `id_message` int(11) NOT NULL AUTO_INCREMENT,
  `id_expediteur` int(11) NOT NULL,
  `id_destinataire` int(11) NOT NULL,
  `message` text NOT NULL,
  `lu` tinyint(1) DEFAULT 0,
  `date_envoi` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_message`),
  KEY `id_expediteur` (`id_expediteur`),
  KEY `id_destinataire` (`id_destinataire`),
  CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`id_expediteur`) REFERENCES `users` (`id_user`) ON DELETE CASCADE,
  CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`id_destinataire`) REFERENCES `users` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_messages`
--

LOCK TABLES `chat_messages` WRITE;
/*!40000 ALTER TABLE `chat_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `chat_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classes`
--

DROP TABLE IF EXISTS `classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `classes` (
  `id_classe` int(11) NOT NULL AUTO_INCREMENT,
  `niveau` varchar(50) DEFAULT NULL,
  `id_dept` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_classe`),
  KEY `id_dept` (`id_dept`),
  CONSTRAINT `fk_classes_departements` FOREIGN KEY (`id_dept`) REFERENCES `departements` (`id_dept`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classes`
--

LOCK TABLES `classes` WRITE;
/*!40000 ALTER TABLE `classes` DISABLE KEYS */;
INSERT INTO `classes` VALUES (1,'BAC I',1),(5,'BAC I',2),(6,'BAC III',2),(8,'BAC II',6),(9,'BAC II',6),(11,'BAC 6',16),(12,'BAC V',15),(13,'BAC V',18);
/*!40000 ALTER TABLE `classes` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_notif_insert_classe AFTER INSERT ON classes
FOR EACH ROW
BEGIN
    DECLARE v_dept VARCHAR(100);
    SELECT nom_dept INTO v_dept FROM departements WHERE id_dept = NEW.id_dept;
    INSERT INTO notifications (source_table, id_dept, type_evenement, titre, message, statut, date_creation)
    VALUES ('classes', NEW.id_dept, 'SUCCES',
        CONCAT('Nouvelle classe : ', NEW.niveau),
        CONCAT('La classe "', NEW.niveau, '" a été créée pour le département ', COALESCE(v_dept, 'N/A')),
        'non_lu', NOW());
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_notif_update_classe` AFTER UPDATE ON `classes`
 FOR EACH ROW 
BEGIN
    INSERT INTO notifications (titre, message, type_evenement, statut, date_creation)
    VALUES (
        CONCAT('Classe modifiée : ', OLD.niveau),
        CONCAT('La classe "', OLD.niveau, '" a été renommée ou réaffectée en "', NEW.niveau, '".'),
        'ALERT',
        'non_lu',
        NOW()
    );
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_notif_delete_classe` BEFORE DELETE ON `classes`
 FOR EACH ROW 
BEGIN
    INSERT INTO notifications (titre, message, type_evenement, statut, date_creation)
    VALUES (
        CONCAT('Classe supprimée : ', OLD.niveau),
        CONCAT('La classe "', OLD.niveau, '" a été définitivement fermée et retirée de la base de données.'),
        'URGENT',
        'non_lu',
        NOW()
    );
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `codes_2fa`
--

DROP TABLE IF EXISTS `codes_2fa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `codes_2fa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `tentatives` int(11) NOT NULL DEFAULT 0,
  `dernier_envoi` datetime NOT NULL,
  `debut_periode_envoi` datetime NOT NULL,
  `nombre_envois` int(11) NOT NULL DEFAULT 1,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_user` (`id_user`),
  CONSTRAINT `fk_codes_2fa_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `codes_2fa`
--

LOCK TABLES `codes_2fa` WRITE;
/*!40000 ALTER TABLE `codes_2fa` DISABLE KEYS */;
/*!40000 ALTER TABLE `codes_2fa` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commentaires_supports`
--

DROP TABLE IF EXISTS `commentaires_supports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `commentaires_supports` (
  `id_commentaire` int(11) NOT NULL AUTO_INCREMENT,
  `id_support` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `contenu` text NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_commentaire`),
  KEY `id_support` (`id_support`),
  KEY `id_user` (`id_user`),
  CONSTRAINT `commentaires_supports_ibfk_1` FOREIGN KEY (`id_support`) REFERENCES `supports_cours` (`id_support`) ON DELETE CASCADE,
  CONSTRAINT `commentaires_supports_ibfk_2` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commentaires_supports`
--

LOCK TABLES `commentaires_supports` WRITE;
/*!40000 ALTER TABLE `commentaires_supports` DISABLE KEYS */;
/*!40000 ALTER TABLE `commentaires_supports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comptables`
--

DROP TABLE IF EXISTS `comptables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comptables` (
  `id_comptable` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  PRIMARY KEY (`id_comptable`),
  KEY `id_user` (`id_user`),
  CONSTRAINT `comptables_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comptables`
--

LOCK TABLES `comptables` WRITE;
/*!40000 ALTER TABLE `comptables` DISABLE KEYS */;
INSERT INTO `comptables` VALUES (1,21);
/*!40000 ALTER TABLE `comptables` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `configuration`
--

DROP TABLE IF EXISTS `configuration`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `configuration` (
  `id_config` int(11) NOT NULL AUTO_INCREMENT,
  `cle` varchar(80) NOT NULL COMMENT 'Identifiant unique du paramètre',
  `valeur` text DEFAULT NULL COMMENT 'Valeur du paramètre',
  `categorie` varchar(50) DEFAULT 'general' COMMENT 'universite | application',
  `libelle` varchar(150) DEFAULT NULL COMMENT 'Nom lisible du paramètre',
  `type` enum('text','email','url','tel','textarea','image') DEFAULT 'text',
  `date_modification` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_config`),
  UNIQUE KEY `cle` (`cle`),
  KEY `idx_categorie` (`categorie`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `configuration`
--

LOCK TABLES `configuration` WRITE;
/*!40000 ALTER TABLE `configuration` DISABLE KEYS */;
INSERT INTO `configuration` VALUES (1,'univ_nom_officiel','Université du Lac Tanganyika','universite','Nom officiel de l\'université','text','2026-10-16 17:32:39'),(2,'univ_nom_abrege','ULT','universite','Nom abrégé','text','2026-10-16 17:32:39'),(3,'univ_adresse','','universite','Adresse physique','textarea','2026-10-16 17:32:39'),(4,'univ_ville','Bujumbura','universite','Ville','text','2026-10-16 17:32:39'),(5,'univ_pays','Burundi','universite','Pays','text','2026-10-16 17:32:39'),(6,'univ_telephone','','universite','Téléphone officiel','tel','2026-10-16 17:32:39'),(7,'univ_email','','universite','Email officiel','email','2026-10-16 17:32:39'),(8,'univ_site_web','','universite','Site web officiel','url','2026-10-16 17:32:39'),(9,'univ_logo','','universite','Logo de l\'université','image','2026-10-16 17:32:39'),(10,'app_nom','UniVerse','application','Nom de l\'application','text','2026-10-16 17:32:39'),(11,'app_version','2.0','application','Version','text','2026-10-16 17:32:39'),(12,'app_email_contact','','application','Email de contact','email','2026-10-16 17:32:39'),(13,'app_fuseau','Africa/Bujumbura','application','Fuseau horaire','text','2026-10-16 17:32:39');
/*!40000 ALTER TABLE `configuration` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cours`
--

DROP TABLE IF EXISTS `cours`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cours` (
  `id_cours` int(11) NOT NULL AUTO_INCREMENT,
  `nom_cours` varchar(150) DEFAULT NULL,
  `credit` int(11) DEFAULT NULL,
  `categorie` varchar(20) DEFAULT NULL,
  `semestre` varchar(20) DEFAULT NULL,
  `id_prof` int(11) DEFAULT NULL,
  `id_classe` int(11) NOT NULL,
  PRIMARY KEY (`id_cours`),
  KEY `id_prof` (`id_prof`),
  KEY `fk_cours_classe` (`id_classe`),
  CONSTRAINT `fk_cours_classes` FOREIGN KEY (`id_classe`) REFERENCES `classes` (`id_classe`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cours_professeurs` FOREIGN KEY (`id_prof`) REFERENCES `professeurs` (`id_prof`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cours`
--

LOCK TABLES `cours` WRITE;
/*!40000 ALTER TABLE `cours` DISABLE KEYS */;
INSERT INTO `cours` VALUES (14,'Cloud Computing',4,'En attente','Semestre 2',3,1),(15,'Intelligence Artificielle',4,'En attente','Semestre 1',3,1);
/*!40000 ALTER TABLE `cours` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_notif_insert_cours AFTER INSERT ON cours
FOR EACH ROW
BEGIN
    DECLARE v_id_dept INT;
    SELECT c.id_dept INTO v_id_dept FROM classes cl JOIN departements c ON cl.id_dept = c.id_dept WHERE cl.id_classe = NEW.id_classe;
    INSERT INTO notifications (source_table, id_dept, type_evenement, titre, message, statut, date_creation)
    VALUES ('cours', v_id_dept, 'SUCCES',
        CONCAT('Nouveau cours : ', COALESCE(NEW.nom_cours, 'Sans nom')),
        CONCAT('Le cours "', COALESCE(NEW.nom_cours, 'Sans nom'), '" a été ajouté.'),
        'non_lu', NOW());
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_notif_update_cours` AFTER UPDATE ON `cours`
 FOR EACH ROW 
BEGIN
    -- On ne déclenche la notification que si le nom, les crédits ou le semestre ont changé
    IF (OLD.nom_cours <> NEW.nom_cours OR OLD.credit <> NEW.credit OR OLD.semestre <> NEW.semestre) THEN
        INSERT INTO notifications (titre, message, type_evenement, statut, date_creation)
        VALUES (
            CONCAT('Cours modifié : ', OLD.nom_cours),
            CONCAT('Le cours de "', OLD.nom_cours, '" a été mis à jour. ',
                   'Nouvelles infos -> Nom: ', NEW.nom_cours, 
                   ', Crédits: ', NEW.credit, 
                   ', Semestre: ', NEW.semestre, '.'),
            'ALERT', -- Badge Orange pour une modification
            'non_lu',
            NOW()
        );
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_notif_delete_cours` BEFORE DELETE ON `cours`
 FOR EACH ROW 
BEGIN
    INSERT INTO notifications (titre, message, type_evenement, statut, date_creation)
    VALUES (
        CONCAT('Cours supprimé : ', OLD.nom_cours),
        CONCAT('Le cours de "', OLD.nom_cours, '" (Catégorie: ', OLD.categorie, ', Semestre: ', OLD.semestre, ') a été définitivement retiré des programmes.'),
        'URGENT',
        'non_lu',
        NOW()
    );
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `demandes_academiques`
--

DROP TABLE IF EXISTS `demandes_academiques`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `demandes_academiques` (
  `id_demande` int(11) NOT NULL AUTO_INCREMENT,
  `id_prof` int(11) NOT NULL,
  `type_demande` enum('changement_horaire','changement_salle','report_examen','autre') NOT NULL,
  `sujet` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `statut` enum('en_attente','acceptee','refusee','en_traitement') DEFAULT 'en_attente',
  `date_creation` datetime DEFAULT current_timestamp(),
  `date_traitement` datetime DEFAULT NULL,
  `reponse` text DEFAULT NULL,
  PRIMARY KEY (`id_demande`),
  KEY `id_prof` (`id_prof`),
  CONSTRAINT `demandes_academiques_ibfk_1` FOREIGN KEY (`id_prof`) REFERENCES `professeurs` (`id_prof`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `demandes_academiques`
--

LOCK TABLES `demandes_academiques` WRITE;
/*!40000 ALTER TABLE `demandes_academiques` DISABLE KEYS */;
/*!40000 ALTER TABLE `demandes_academiques` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `demandes_information`
--

DROP TABLE IF EXISTS `demandes_information`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `demandes_information` (
  `id_demande` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `id_dept` int(11) DEFAULT NULL,
  `nom_dept` varchar(150) DEFAULT NULL,
  `faculte` varchar(150) DEFAULT NULL,
  `niveau_souhaite` varchar(50) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `statut` enum('nouveau','traite','archive') DEFAULT 'nouveau',
  `adresse_ip` varchar(45) DEFAULT NULL,
  `date_demande` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_demande`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `demandes_information`
--

LOCK TABLES `demandes_information` WRITE;
/*!40000 ALTER TABLE `demandes_information` DISABLE KEYS */;
/*!40000 ALTER TABLE `demandes_information` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departements`
--

DROP TABLE IF EXISTS `departements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departements` (
  `id_dept` int(11) NOT NULL AUTO_INCREMENT,
  `nom_dept` varchar(100) DEFAULT NULL,
  `faculte` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id_dept`),
  KEY `idx_faculte` (`faculte`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departements`
--

LOCK TABLES `departements` WRITE;
/*!40000 ALTER TABLE `departements` DISABLE KEYS */;
INSERT INTO `departements` VALUES (1,'INFORMATIQUE','Faculté d’Informatique','departement d\'info'),(2,'FSGEA','Faculté des Sciences de Gestion et d’Économie Appliquée','Faculte des Sciences de Gestion et Economie Applique'),(6,'Electronique','Faculté d’Électronique','D?partement d\'?lectronique'),(13,'FSI','Faculté des Sciences de l’Ingénieur','Faculte Science et Ingeniers s'),(14,'SPRI','Faculté des Sciences Sociales et Politique','Science Politique et Relation International'),(15,'Genie Civil','Faculté des Sciences et Technologies','batiment'),(16,'Batiment','Faculté des Sciences et Technologies','dfezr'),(18,'Droit International','Faculté de Droit','Filière de la faculté'),(19,'Bâtiments et Infrastructures','Faculté des Sciences de l’Ingénieur','Filière de la faculté'),(20,'Génie Hydraulique','Faculté des Sciences de l’Ingénieur','Filière de la faculté'),(21,'Génie électrique','Faculté des Sciences de l’Ingénieur','Filière de la faculté'),(22,'Génie Mécanique et énergétique','Faculté des Sciences de l’Ingénieur','Filière de la faculté'),(23,'Aménagement du Territoire','Faculté des Sciences de l’Ingénieur','Filière de la faculté'),(24,'Urbanisme et Habitat','Faculté des Sciences de l’Ingénieur','Filière de la faculté'),(25,'Science Politique et Relations Internationales','Faculté des Sciences Sociales et Politique','Filière de la faculté'),(26,'Sociologie','Faculté des Sciences Sociales et Politique','Filière de la faculté'),(27,'Communication organisationnelle','Faculté des Sciences Sociales et Politique','Filière de la faculté'),(28,'Mass médias','Faculté des Sciences Sociales et Politique','Filière de la faculté'),(29,'Économie et commerce international','Faculté des Sciences de Gestion et d’Économie Appliquée','Filière de la faculté'),(30,'Économie des ressources naturelles et de l’environnement','Faculté des Sciences de Gestion et d’Économie Appliquée','Filière de la faculté'),(31,'Économie agricole','Faculté des Sciences de Gestion et d’Économie Appliquée','Filière de la faculté'),(32,'Audit et contrôle de gestion','Faculté des Sciences de Gestion et d’Économie Appliquée','Filière de la faculté'),(33,'Marketing et management','Faculté des Sciences de Gestion et d’Économie Appliquée','Filière de la faculté'),(34,'Finance et comptabilité','Faculté des Sciences de Gestion et d’Économie Appliquée','Filière de la faculté'),(35,'Génie Logiciel','Faculté d’Informatique','Filière de la faculté'),(36,'Ingénierie réseaux','Faculté d’Informatique','Filière de la faculté'),(37,'Informatique de Gestion','Faculté d’Informatique','Filière de la faculté'),(38,'Génie informatique','Faculté d’Informatique','Filière de la faculté'),(39,'Télécommunication et Réseaux','Faculté d’Informatique','Filière de la faculté');
/*!40000 ALTER TABLE `departements` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_notif_insert_dept AFTER INSERT ON departements
FOR EACH ROW
BEGIN
    INSERT INTO notifications (source_table, id_dept, type_evenement, titre, message, statut, date_creation)
    VALUES ('departements', NEW.id_dept, 'INFO',
        CONCAT('Nouveau Département : ', COALESCE(NEW.nom_dept, 'Sans nom')),
        CONCAT('Le département "', COALESCE(NEW.nom_dept, 'Sans nom'), '" a été créé.'),
        'non_lu', NOW());
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_notif_update_dept` AFTER UPDATE ON `departements`
 FOR EACH ROW 
BEGIN
    IF (OLD.nom_dept <> NEW.nom_dept) THEN
        INSERT INTO notifications (titre, message, type_evenement, statut, date_creation)
        VALUES (
            CONCAT('Département modifié : ', OLD.nom_dept),
            CONCAT('Le département "', OLD.nom_dept, '" a été renommé en "', NEW.nom_dept, '".'),
            'ALERT',
            'non_lu',
            NOW()
        );
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_notif_delete_dept` BEFORE DELETE ON `departements`
 FOR EACH ROW 
BEGIN
    INSERT INTO notifications (titre, message, type_evenement, statut, date_creation)
    VALUES (
        CONCAT('Département supprimé : ', OLD.nom_dept),
        CONCAT('Le département universitaire "', OLD.nom_dept, '" a été retiré de la base de données avec succès.'),
        'URGENT',
        'non_lu',
        NOW()
    );
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `doyens`
--

DROP TABLE IF EXISTS `doyens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `doyens` (
  `id_doyen` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `id_dept` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_doyen`),
  KEY `id_user` (`id_user`),
  CONSTRAINT `doyens_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doyens`
--

LOCK TABLES `doyens` WRITE;
/*!40000 ALTER TABLE `doyens` DISABLE KEYS */;
INSERT INTO `doyens` VALUES (1,9,1);
/*!40000 ALTER TABLE `doyens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `etudiants`
--

DROP TABLE IF EXISTS `etudiants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `etudiants` (
  `id_etudiant` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `matricule` varchar(50) DEFAULT NULL,
  `date_naissance` varchar(20) DEFAULT NULL,
  `id_classe` int(11) DEFAULT NULL,
  `titre` enum('Nouveau','Redoublant') DEFAULT NULL,
  `annee_academique_entree` varchar(9) DEFAULT NULL COMMENT 'Ex: 2025-2026',
  PRIMARY KEY (`id_etudiant`),
  UNIQUE KEY `matricule` (`matricule`),
  KEY `id_user` (`id_user`),
  KEY `id_classe` (`id_classe`),
  CONSTRAINT `etudiants_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE,
  CONSTRAINT `etudiants_ibfk_2` FOREIGN KEY (`id_classe`) REFERENCES `classes` (`id_classe`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `etudiants`
--

LOCK TABLES `etudiants` WRITE;
/*!40000 ALTER TABLE `etudiants` DISABLE KEYS */;
INSERT INTO `etudiants` VALUES (1,3,'','2003-10-04',1,'Nouveau',NULL),(2,5,'FSGE-2026-5998@UNIV','2003-10-04',6,'Nouveau',NULL),(3,6,'FSGE-2026-1211@UNIV','2003-06-16',5,'Nouveau',NULL),(4,13,'INFO-2026-1987@UNIV','2005-09-07',1,'Nouveau','2025-2026');
/*!40000 ALTER TABLE `etudiants` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_generate_matricule_etudiant` 
BEFORE INSERT ON `etudiants`
FOR EACH ROW
BEGIN
    DECLARE v_nom_dept VARCHAR(100);
    DECLARE v_annee VARCHAR(4);
    DECLARE v_prefixe VARCHAR(50);
    DECLARE v_suffixe VARCHAR(50);
    DECLARE v_random INT;
    DECLARE v_matricule VARCHAR(100);
    DECLARE v_exists INT DEFAULT 1;

    -- 1. Extraction de l'année pour le matricule
    -- Si NEW.annee_academique_entree n'existe pas dans ta table 'etudiants',
    -- on prend par défaut l'année en cours.
    SET v_annee = CAST(YEAR(CURDATE()) AS CHAR(4));

    -- 2. Récupération du nom du département lié à la classe de l'étudiant
    -- Liaison corrigée : classes.id_dept -> departements.id_dept
    SELECT d.nom_dept INTO v_nom_dept
    FROM classes c
    INNER JOIN departements d ON c.id_dept = d.id_dept
    WHERE c.id_classe = NEW.id_classe
    LIMIT 1;

    -- 3. Construction du préfixe à partir du nom du département
    -- On prend les 4 premières lettres en majuscules sans espaces
    IF v_nom_dept IS NOT NULL THEN
        SET v_prefixe = UPPER(LEFT(REPLACE(v_nom_dept, ' ', ''), 4));
    ELSE
        SET v_prefixe = 'UNIV';
    END IF;

    -- 4. Récupération du suffixe (nom_site) depuis la table configuration_site
    -- Utilisation d'un bloc BEGIN/DECLARE ou d'un simple gestionnaire pour éviter les erreurs si la table n'existe pas
    BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET v_suffixe = 'UNIV';
        SELECT IFNULL(nom_site, 'UNIV') INTO v_suffixe 
        FROM configuration_site 
        LIMIT 1;
    END;

    -- Sécurité additionnelle si la table existe mais est vide
    IF v_suffixe IS NULL THEN
        SET v_suffixe = 'UNIV';
    END IF;

    -- 5. Boucle pour garantir l'unicité du matricule généré
    WHILE v_exists > 0 DO
        SET v_random = FLOOR(1000 + (RAND() * 9000));
        SET v_matricule = CONCAT(v_prefixe, '-', v_annee, '-', v_random, '@', v_suffixe);
        
        SELECT COUNT(*) INTO v_exists FROM etudiants WHERE matricule = v_matricule;
    END WHILE;

    -- 6. Attribution du matricule généré
    SET NEW.matricule = v_matricule;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_notif_nouvel_etudiant AFTER INSERT ON etudiants
FOR EACH ROW
BEGIN
    DECLARE v_nom VARCHAR(100);
    DECLARE v_prenom VARCHAR(100);
    DECLARE v_niveau VARCHAR(50);
    DECLARE v_id_dept INT;
    
    SELECT COALESCE(nom, ''), COALESCE(prenom, '') INTO v_nom, v_prenom FROM users WHERE id_user = NEW.id_user;
    SELECT niveau, id_dept INTO v_niveau, v_id_dept FROM classes WHERE id_classe = NEW.id_classe;
    
    INSERT INTO notifications (source_table, id_dept, type_evenement, titre, message, statut, date_creation)
    VALUES ('etudiants', v_id_dept, 'SUCCES',
        CONCAT('Nouvelle inscription : ', UPPER(v_nom), ' ', v_prenom),
        CONCAT('L''étudiant ', UPPER(v_nom), ' ', v_prenom, ' (Matricule : ', COALESCE(NEW.matricule, 'N/A'), ') a été inscrit en ', COALESCE(v_niveau, 'N/A')),
        'non_lu', NOW());
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_notif_update_etudiant` AFTER UPDATE ON `etudiants`
 FOR EACH ROW 
BEGIN
    DECLARE v_nom VARCHAR(100);
    DECLARE v_prenom VARCHAR(100);
    
    SELECT nom, prenom INTO v_nom, v_prenom FROM users WHERE id_user = NEW.id_user;

    INSERT INTO notifications (titre, message, type_evenement, statut, date_creation)
    VALUES (
        CONCAT('Mise à jour : ', UPPER(v_nom), ' ', v_prenom),
        CONCAT('Les informations de l\'étudiant ', UPPER(v_nom), ' ', v_prenom, ' (Matricule : ', NEW.matricule, ') ont été modifiées.'),
        'ALERT', -- Badge Orange pour une modification
        'non_lu',
        NOW()
    );
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_notif_delete_etudiant` 
BEFORE DELETE ON `etudiants`
FOR EACH ROW
BEGIN
    -- Déclaration des variables pour stocker les informations associées
    DECLARE v_nom VARCHAR(100);
    DECLARE v_prenom VARCHAR(100);
    DECLARE v_email VARCHAR(150);
    DECLARE v_niveau VARCHAR(50);
    DECLARE v_nom_dept VARCHAR(100);

    -- 1. Récupérer le nom, le prénom et l'email depuis la table 'users'
    SELECT nom, prenom, email 
    INTO v_nom, v_prenom, v_email 
    FROM users 
    WHERE id_user = OLD.id_user;

    -- 2. Récupérer le niveau et le nom du département via la table 'classes'
    SELECT c.niveau, d.nom_dept 
    INTO v_niveau, v_nom_dept
    FROM classes c
    LEFT JOIN departements d ON c.id_dept = d.id_dept
    WHERE c.id_classe = OLD.id_classe;

    -- 3. Insertion dans la table de sauvegarde 'etudiants_backup'
    INSERT INTO `etudiants_backup` (
        `matricule`, 
        `nom`, 
        `prenom`, 
        `email`, 
        `nom_departement`, 
        `niveau`, 
        `date_suppression`
    ) VALUES (
        OLD.`matricule`, 
        v_nom, 
        v_prenom, 
        v_email, 
        v_nom_dept, 
        v_niveau, 
        NOW()
    );
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `etudiants_backup`
--

DROP TABLE IF EXISTS `etudiants_backup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `etudiants_backup` (
  `id_backup` int(11) NOT NULL AUTO_INCREMENT,
  `matricule` varchar(50) DEFAULT NULL,
  `nom` varchar(100) DEFAULT NULL,
  `prenom` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `nom_departement` varchar(100) DEFAULT NULL,
  `niveau` varchar(50) DEFAULT NULL,
  `annee_academique_entree` varchar(9) DEFAULT NULL COMMENT 'Ex: 2025-2026',
  `date_suppression` datetime DEFAULT NULL,
  PRIMARY KEY (`id_backup`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `etudiants_backup`
--

LOCK TABLES `etudiants_backup` WRITE;
/*!40000 ALTER TABLE `etudiants_backup` DISABLE KEYS */;
INSERT INTO `etudiants_backup` VALUES (8,NULL,'Alice',NULL,NULL,'INFORMATIQUE','BAC I',NULL,'2026-06-29 22:12:25');
/*!40000 ALTER TABLE `etudiants_backup` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evenements_calendrier`
--

DROP TABLE IF EXISTS `evenements_calendrier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evenements_calendrier` (
  `id_evenement` int(11) NOT NULL AUTO_INCREMENT,
  `id_prof` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `date_debut` datetime NOT NULL,
  `date_fin` datetime DEFAULT NULL,
  `type_evenement` enum('cours','examen','reunion','autre') DEFAULT 'cours',
  `couleur` varchar(7) DEFAULT '#2563eb',
  PRIMARY KEY (`id_evenement`),
  KEY `id_prof` (`id_prof`),
  CONSTRAINT `evenements_calendrier_ibfk_1` FOREIGN KEY (`id_prof`) REFERENCES `professeurs` (`id_prof`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evenements_calendrier`
--

LOCK TABLES `evenements_calendrier` WRITE;
/*!40000 ALTER TABLE `evenements_calendrier` DISABLE KEYS */;
/*!40000 ALTER TABLE `evenements_calendrier` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `examens`
--

DROP TABLE IF EXISTS `examens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `examens` (
  `id_examen` int(11) NOT NULL AUTO_INCREMENT,
  `id_cours` int(11) NOT NULL,
  `id_classe` int(11) NOT NULL,
  `id_prof` int(11) NOT NULL,
  `id_doyen` int(11) DEFAULT NULL,
  `titre` varchar(255) NOT NULL,
  `type_examen` enum('Interrogation','TP','TD','Examen','Rattrapage') DEFAULT 'Examen',
  `date_prevue` date NOT NULL,
  `heure_debut` time DEFAULT NULL,
  `heure_fin` time DEFAULT NULL,
  `duree` int(11) NOT NULL,
  `points_total` int(11) NOT NULL DEFAULT 20,
  `instructions` text DEFAULT NULL,
  `sujet_fichier` varchar(255) DEFAULT NULL,
  `statut` enum('brouillon','soumis','en_attente','valide','rejete','a_corriger','archive') DEFAULT 'brouillon',
  `date_envoi` datetime DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL,
  `motif_rejet` text DEFAULT NULL,
  PRIMARY KEY (`id_examen`),
  KEY `id_cours` (`id_cours`),
  KEY `id_classe` (`id_classe`),
  KEY `id_prof` (`id_prof`),
  KEY `id_doyen` (`id_doyen`),
  CONSTRAINT `examens_ibfk_1` FOREIGN KEY (`id_cours`) REFERENCES `cours` (`id_cours`) ON DELETE CASCADE,
  CONSTRAINT `examens_ibfk_2` FOREIGN KEY (`id_classe`) REFERENCES `classes` (`id_classe`) ON DELETE CASCADE,
  CONSTRAINT `examens_ibfk_3` FOREIGN KEY (`id_prof`) REFERENCES `professeurs` (`id_prof`) ON DELETE CASCADE,
  CONSTRAINT `examens_ibfk_4` FOREIGN KEY (`id_doyen`) REFERENCES `users` (`id_user`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `examens`
--

LOCK TABLES `examens` WRITE;
/*!40000 ALTER TABLE `examens` DISABLE KEYS */;
/*!40000 ALTER TABLE `examens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `historique_paiements`
--

DROP TABLE IF EXISTS `historique_paiements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `historique_paiements` (
  `id_historique` int(11) NOT NULL AUTO_INCREMENT,
  `id_paiement` int(11) NOT NULL,
  `id_etudiant` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `ancien_statut` varchar(20) DEFAULT NULL,
  `nouveau_statut` varchar(20) DEFAULT NULL,
  `date_action` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_historique`),
  KEY `id_paiement` (`id_paiement`),
  KEY `id_etudiant` (`id_etudiant`),
  CONSTRAINT `historique_paiements_ibfk_1` FOREIGN KEY (`id_paiement`) REFERENCES `paiements` (`id_paiement`) ON DELETE CASCADE,
  CONSTRAINT `historique_paiements_ibfk_2` FOREIGN KEY (`id_etudiant`) REFERENCES `etudiants` (`id_etudiant`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historique_paiements`
--

LOCK TABLES `historique_paiements` WRITE;
/*!40000 ALTER TABLE `historique_paiements` DISABLE KEYS */;
/*!40000 ALTER TABLE `historique_paiements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `horaires`
--

DROP TABLE IF EXISTS `horaires`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `horaires` (
  `id_horaire` int(11) NOT NULL AUTO_INCREMENT,
  `id_cours` int(11) NOT NULL,
  `id_classe` int(11) NOT NULL,
  `id_prof` int(11) DEFAULT NULL,
  `jour` enum('Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi') NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `salle` varchar(100) DEFAULT NULL,
  `type_cours` enum('CM','TD','TP') DEFAULT 'CM',
  `semaine` int(11) DEFAULT 1,
  `date_creation` datetime DEFAULT current_timestamp(),
  `date_modification` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_horaire`),
  KEY `fk_horaires_cours` (`id_cours`),
  KEY `fk_horaires_classe` (`id_classe`),
  KEY `fk_horaires_prof` (`id_prof`),
  KEY `idx_jour` (`jour`),
  KEY `idx_heure` (`heure_debut`,`heure_fin`),
  CONSTRAINT `fk_horaires_classe` FOREIGN KEY (`id_classe`) REFERENCES `classes` (`id_classe`) ON DELETE CASCADE,
  CONSTRAINT `fk_horaires_cours` FOREIGN KEY (`id_cours`) REFERENCES `cours` (`id_cours`) ON DELETE CASCADE,
  CONSTRAINT `fk_horaires_prof` FOREIGN KEY (`id_prof`) REFERENCES `professeurs` (`id_prof`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `horaires`
--

LOCK TABLES `horaires` WRITE;
/*!40000 ALTER TABLE `horaires` DISABLE KEYS */;
/*!40000 ALTER TABLE `horaires` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_validate_horaires_insert
BEFORE INSERT ON horaires
FOR EACH ROW
BEGIN
    IF NEW.heure_debut >= NEW.heure_fin THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'L''heure de début doit être inférieure à l''heure de fin';
    END IF;
    
    IF TIMEDIFF(NEW.heure_fin, NEW.heure_debut) > '03:00:00' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La durée d''un cours ne peut pas dépasser 3 heures';
    END IF;
    
    IF NEW.heure_debut < '06:00:00' OR NEW.heure_fin > '22:00:00' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Les horaires doivent être compris entre 06:00 et 22:00';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_validate_horaires_update
BEFORE UPDATE ON horaires
FOR EACH ROW
BEGIN
    IF NEW.heure_debut >= NEW.heure_fin THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'L''heure de début doit être inférieure à l''heure de fin';
    END IF;
    
    IF TIMEDIFF(NEW.heure_fin, NEW.heure_debut) > '03:00:00' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La durée d''un cours ne peut pas dépasser 3 heures';
    END IF;
    
    IF NEW.heure_debut < '06:00:00' OR NEW.heure_fin > '22:00:00' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Les horaires doivent être compris entre 06:00 et 22:00';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `inscriptions`
--

DROP TABLE IF EXISTS `inscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inscriptions` (
  `id_inscription` int(11) NOT NULL AUTO_INCREMENT,
  `id_etudiant` int(11) DEFAULT NULL,
  `date_inscription` date DEFAULT NULL,
  PRIMARY KEY (`id_inscription`),
  KEY `inscriptions_ibfk_1` (`id_etudiant`),
  CONSTRAINT `inscriptions_ibfk_1` FOREIGN KEY (`id_etudiant`) REFERENCES `etudiants` (`id_etudiant`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inscriptions`
--

LOCK TABLES `inscriptions` WRITE;
/*!40000 ALTER TABLE `inscriptions` DISABLE KEYS */;
INSERT INTO `inscriptions` VALUES (18,50,'2026-06-09'),(23,57,'2026-06-20');
/*!40000 ALTER TABLE `inscriptions` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trig_verifier_inscription

BEFORE INSERT ON inscriptions

FOR EACH ROW



BEGIN



    IF EXISTS (

        SELECT 1

        FROM inscriptions

        WHERE id_etudiant = NEW.id_etudiant

    ) THEN



        SIGNAL SQLSTATE '45000'

        SET MESSAGE_TEXT = 'Cet etudiant est deja inscrit';



    END IF;



END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trig_before_update_date_inscription

BEFORE UPDATE ON inscriptions

 FOR EACH ROW BEGIN



    DECLARE v_annee INT;

    DECLARE v_mois INT;

    DECLARE v_jour INT;



    -- extraire année, mois, jour

    SET v_annee = YEAR(NEW.date_inscription);

    SET v_mois  = MONTH(NEW.date_inscription);

    SET v_jour  = DAY(NEW.date_inscription);



    -- vérifier année (4 chiffres) + mois (1 à 12) + jour (1 à 31)

    IF v_annee < 1000 OR v_annee > YEAR(CURDATE()) OR v_mois < 1 OR v_mois > 12 OR v_jour < 1 OR v_jour > 31 THEN

        SIGNAL SQLSTATE '45000'

        SET MESSAGE_TEXT = "Date d'inscription invalide";



    END IF;



END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_journal_modification_inscription` AFTER UPDATE ON `inscriptions` FOR EACH ROW BEGIN



    -- Enregistrer seulement si la date change

    IF OLD.date_inscription <> NEW.date_inscription THEN



        INSERT INTO journal_modification_inscription(

            id_inscription,

            ancienne_date_inscription,

            nouvelle_date_inscription,

            date_modification

        )

        VALUES(

            OLD.id_inscription,

            OLD.date_inscription,

            NEW.date_inscription,

            NOW()

        );



    END IF;



END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `triger_backup_inscription` BEFORE DELETE ON `inscriptions` FOR EACH ROW BEGIN

INSERT INTO inscriptions_backup(

    id_inscription,

    id_etudiant,

    matricule,

    date_inscription,

    date_suppression

)

VALUES (

    OLD.id_inscription,

    OLD.id_etudiant,

    (SELECT matricule FROM etudiants WHERE id_etudiant = OLD.id_etudiant),

    OLD.date_inscription,

    NOW()

);

END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_suppression_inscription



AFTER DELETE

ON inscriptions



FOR EACH ROW



BEGIN



    INSERT INTO journal_suppression_inscriptions(

        id_inscription,

        id_etudiant,

        date_inscription,

        date_suppression

    )

    VALUES(

        OLD.id_inscription,

        OLD.id_etudiant,

        OLD.date_inscription,

        NOW()

    );



END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `inscriptions_backup`
--

DROP TABLE IF EXISTS `inscriptions_backup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inscriptions_backup` (
  `id_backup` int(11) NOT NULL AUTO_INCREMENT,
  `id_inscription` int(11) DEFAULT NULL,
  `id_etudiant` int(11) DEFAULT NULL,
  `matricule` varchar(50) DEFAULT NULL,
  `date_inscription` date DEFAULT NULL,
  `date_suppression` datetime DEFAULT NULL,
  PRIMARY KEY (`id_backup`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inscriptions_backup`
--

LOCK TABLES `inscriptions_backup` WRITE;
/*!40000 ALTER TABLE `inscriptions_backup` DISABLE KEYS */;
/*!40000 ALTER TABLE `inscriptions_backup` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `journal_modification_etudiants`
--

DROP TABLE IF EXISTS `journal_modification_etudiants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_modification_etudiants` (
  `id_journal_etudiant` int(11) NOT NULL AUTO_INCREMENT,
  `ancien_id_etudiant` int(11) DEFAULT NULL,
  `ancien_nom` varchar(100) DEFAULT NULL,
  `nouveau_nom` varchar(100) DEFAULT NULL,
  `ancien_prenom` varchar(100) DEFAULT NULL,
  `nouveau_prenom` varchar(100) DEFAULT NULL,
  `ancien_date_naissance` date DEFAULT NULL,
  `nouveau_date_naissance` date DEFAULT NULL,
  `ancien_email` varchar(100) DEFAULT NULL,
  `nouveau_email` varchar(100) DEFAULT NULL,
  `ancien_telephone` varchar(100) DEFAULT NULL,
  `nouveau_telephone` varchar(100) DEFAULT NULL,
  `date_modification` datetime DEFAULT NULL,
  `matricule` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_journal_etudiant`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `journal_modification_etudiants`
--

LOCK TABLES `journal_modification_etudiants` WRITE;
/*!40000 ALTER TABLE `journal_modification_etudiants` DISABLE KEYS */;
INSERT INTO `journal_modification_etudiants` VALUES (1,10,'Ndereyimana','GASAGE','Paul','KING','2001-04-05','0000-00-00','paul.ndere@gmail.com','nkx@gmail.com','+25766000010','66556786','2026-05-30 09:53:31',NULL),(5,19,'CIZA','BINTU','brian','Tresor','0000-00-00','2006-05-16','cizabri@gmail.com','bibi@gmail.com','64098765','67993456','2026-05-30 14:38:52','FSG-2026-1862@UNIV'),(6,28,'ARAKAZA','CUBAHIRO','babu','Tresor','2006-05-16','2006-05-16','Tarabu@gmail.com','bii@gmail.com','78345456','60993456','2026-05-31 00:48:55','INF-2026-8991@UNIV'),(7,42,'KUBWAYO','NDIHO','Benjamen','Anacled','2004-05-09','2001-07-07','benjos@gmail.tagre','ndiho@gmai34.com','12345679','66556704','2026-06-07 07:56:07','FSG-2026-3316@UNIV'),(8,43,'MINANI','MABUJA','Abeee','Abuduri','2003-05-09','2003-02-03','mijos@g.mail.tagre','mijos@g.mail.ta9gre','12345677','76435678','2026-06-07 09:54:31','FSG-2026-3498@UNIV'),(9,44,'IRAKOZE','IRAKIZA','Junior','Junior','2003-05-09','2003-02-03','ijos@gmail.tagre','ijjos@g.mail.ta9gre','+62345677','76435689','2026-06-07 23:55:13','FSG-2026-1079@UNIV'),(10,44,'IRAKIZA','ARAKIZA','Junior','Jinior','2003-02-03','2003-02-03','ijjos@g.mail.ta9gre','ijjos@g.mail.ta9gre','76435689','76435689','2026-06-08 00:08:46','FSG-2026-1079@UNIV'),(11,46,'NIYO','NINI','Didas','Damas','2007-05-09','2003-02-03','nrajos@.gmail.tagre','ijjos@g.mail.tagre','67665395','76435689','2026-06-08 00:24:15','FSG-2026-0867@UNIV'),(12,47,'NIYONKURU','NIYOKINDI','Anacle','Damas','2007-05-09','2003-02-03','nini@gmail.com','niyo@gmail.com','67665395','76435674','2026-06-08 00:49:47','FSG-2026-0646@UNIV');
/*!40000 ALTER TABLE `journal_modification_etudiants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `journal_modification_inscription`
--

DROP TABLE IF EXISTS `journal_modification_inscription`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_modification_inscription` (
  `id_journal` int(11) NOT NULL AUTO_INCREMENT,
  `id_inscription` int(11) DEFAULT NULL,
  `ancienne_date_inscription` date DEFAULT NULL,
  `nouvelle_date_inscription` date DEFAULT NULL,
  `date_modification` datetime DEFAULT NULL,
  PRIMARY KEY (`id_journal`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `journal_modification_inscription`
--

LOCK TABLES `journal_modification_inscription` WRITE;
/*!40000 ALTER TABLE `journal_modification_inscription` DISABLE KEYS */;
INSERT INTO `journal_modification_inscription` VALUES (1,2,'2024-01-19','2026-08-23','2026-05-31 01:40:21'),(3,12,'2026-06-05','0206-08-23','2026-06-05 07:42:55'),(4,12,'0206-08-23','0000-00-00','2026-06-05 07:56:42'),(5,12,'0000-00-00','2020-08-23','2026-06-05 07:58:30'),(6,12,'2020-08-23','0000-00-00','2026-06-05 07:59:21'),(7,12,'0000-00-00','2012-04-21','2026-06-05 08:05:12'),(11,12,'2012-04-21','2002-10-04','2026-06-05 08:07:33'),(13,12,'2002-10-04','2012-03-04','2026-06-05 08:13:47'),(14,12,'2012-03-04','2013-05-23','2026-06-05 08:15:42'),(17,12,'2013-05-23','2002-04-04','2026-06-05 08:19:21'),(18,14,'2026-06-05','2026-03-04','2026-06-05 08:36:19'),(19,15,'2026-06-08','2026-05-08','2026-06-08 01:07:44'),(20,17,'2026-06-08','2026-05-23','2026-06-08 02:41:53');
/*!40000 ALTER TABLE `journal_modification_inscription` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `journal_suppression_etudiants`
--

DROP TABLE IF EXISTS `journal_suppression_etudiants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_suppression_etudiants` (
  `id_journal` int(11) NOT NULL AUTO_INCREMENT,
  `id_etudiant` int(11) DEFAULT NULL,
  `matricule` varchar(50) DEFAULT NULL,
  `nom` varchar(100) DEFAULT NULL,
  `prenom` varchar(100) DEFAULT NULL,
  `date_naissance` date DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `departement` varchar(100) DEFAULT NULL,
  `niveau` varchar(50) DEFAULT NULL,
  `date_suppression` datetime DEFAULT NULL,
  PRIMARY KEY (`id_journal`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `journal_suppression_etudiants`
--

LOCK TABLES `journal_suppression_etudiants` WRITE;
/*!40000 ALTER TABLE `journal_suppression_etudiants` DISABLE KEYS */;
INSERT INTO `journal_suppression_etudiants` VALUES (1,25,'FSG-2026-8876@UNIV','KILIKU','zaya','2002-06-23','kili@gmail.com','+65668991','FSGEA','BAC I','2026-05-30 20:20:41'),(2,20,'FSG-2026-4633@UNIV','KEZA','diella','2003-06-16','kazediella@gmail.com','67665345','FSGEA','BAC I','2026-05-30 20:26:18'),(3,23,'FSG-2026-6036@UNIV','BUCURA','boris','2005-07-23','bobobu@gmail.com','65438905','FSGEA','BAC III','2026-05-30 20:49:39'),(4,28,'INF-2026-8991@UNIV','CUBAHIRO','Tresor','2006-05-16','bii@gmail.com','60993456','INFORMATIQUE','BAC III','2026-05-31 00:49:28'),(5,42,'FSG-2026-3316@UNIV','NDIHO','Anacled','2001-07-07','ndiho@gmai34.com','66556704','FSGEA','BAC III','2026-06-07 09:34:38'),(6,43,'FSG-2026-3498@UNIV','MABUJA','Abuduri','2003-02-03','mijos@g.mail.ta9gre','76435678','FSGEA','BAC III','2026-06-07 09:56:50'),(7,45,'FSG-2026-3546@UNIV','ARAKAZA','Joline','2007-05-09','arajos@.gmail.tagre','67665345','FSGEA','BAC III','2026-06-08 00:15:22'),(8,47,'FSG-2026-0646@UNIV','NIYOKINDI','Damas','2003-02-03','niyo@gmail.com','76435674','FSGEA','BAC III','2026-06-08 00:54:38'),(12,12,NULL,'Alice',NULL,NULL,NULL,NULL,'INFORMATIQUE','BAC I','2026-06-29 22:12:25');
/*!40000 ALTER TABLE `journal_suppression_etudiants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `journal_suppression_inscriptions`
--

DROP TABLE IF EXISTS `journal_suppression_inscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_suppression_inscriptions` (
  `id_journal` int(11) NOT NULL AUTO_INCREMENT,
  `id_inscription` int(11) DEFAULT NULL,
  `id_etudiant` int(11) DEFAULT NULL,
  `date_inscription` date DEFAULT NULL,
  `date_suppression` datetime DEFAULT NULL,
  PRIMARY KEY (`id_journal`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `journal_suppression_inscriptions`
--

LOCK TABLES `journal_suppression_inscriptions` WRITE;
/*!40000 ALTER TABLE `journal_suppression_inscriptions` DISABLE KEYS */;
INSERT INTO `journal_suppression_inscriptions` VALUES (1,9,9,'2024-01-18','2026-05-30 06:06:58'),(2,5,5,'2024-01-14','2026-05-30 09:32:41'),(3,11,19,'2025-04-23','2026-05-31 01:02:22'),(4,14,38,'2026-03-04','2026-06-05 08:47:10'),(5,15,41,'2026-05-08','2026-06-08 01:11:22'),(6,2,19,'2026-08-23','2026-06-08 01:22:40'),(7,12,29,'2002-04-04','2026-06-08 01:33:53'),(8,16,40,'2026-06-08','2026-06-08 01:57:42'),(9,17,49,'2026-05-23','2026-06-08 02:44:11');
/*!40000 ALTER TABLE `journal_suppression_inscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lecteurs`
--

DROP TABLE IF EXISTS `lecteurs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lecteurs` (
  `id_lecteur` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  PRIMARY KEY (`id_lecteur`),
  KEY `id_user` (`id_user`),
  CONSTRAINT `lecteurs_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lecteurs`
--

LOCK TABLES `lecteurs` WRITE;
/*!40000 ALTER TABLE `lecteurs` DISABLE KEYS */;
/*!40000 ALTER TABLE `lecteurs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id_attempt` int(11) NOT NULL AUTO_INCREMENT,
  `email_auth` varchar(150) NOT NULL,
  `adresse_ip` varchar(45) DEFAULT NULL,
  `succes` tinyint(1) NOT NULL DEFAULT 0,
  `date_tentative` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_attempt`),
  KEY `idx_email` (`email_auth`),
  KEY `idx_ip` (`adresse_ip`),
  KEY `idx_date` (`date_tentative`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id_message` int(11) NOT NULL AUTO_INCREMENT,
  `id_expediteur` int(11) NOT NULL,
  `id_destinataire` int(11) NOT NULL,
  `sujet` varchar(255) NOT NULL,
  `contenu` text NOT NULL,
  `date_envoi` datetime DEFAULT current_timestamp(),
  `lu` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id_message`),
  KEY `id_expediteur` (`id_expediteur`),
  KEY `id_destinataire` (`id_destinataire`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`id_expediteur`) REFERENCES `users` (`id_user`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`id_destinataire`) REFERENCES `users` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
INSERT INTO `messages` VALUES (1,12,9,'Examen','samedi il y aura examen','2026-10-27 12:35:53',0);
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages_professeurs`
--

DROP TABLE IF EXISTS `messages_professeurs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages_professeurs` (
  `id_message` int(11) NOT NULL AUTO_INCREMENT,
  `id_prof` int(11) NOT NULL,
  `id_etudiant` int(11) DEFAULT NULL,
  `id_classe` int(11) DEFAULT NULL,
  `id_cours` int(11) DEFAULT NULL,
  `sujet` varchar(255) NOT NULL,
  `contenu` text NOT NULL,
  `date_envoi` datetime DEFAULT current_timestamp(),
  `lu` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id_message`),
  KEY `id_prof` (`id_prof`),
  KEY `id_etudiant` (`id_etudiant`),
  KEY `id_classe` (`id_classe`),
  KEY `id_cours` (`id_cours`),
  CONSTRAINT `messages_professeurs_ibfk_1` FOREIGN KEY (`id_prof`) REFERENCES `professeurs` (`id_prof`) ON DELETE CASCADE,
  CONSTRAINT `messages_professeurs_ibfk_2` FOREIGN KEY (`id_etudiant`) REFERENCES `etudiants` (`id_etudiant`) ON DELETE CASCADE,
  CONSTRAINT `messages_professeurs_ibfk_3` FOREIGN KEY (`id_classe`) REFERENCES `classes` (`id_classe`) ON DELETE CASCADE,
  CONSTRAINT `messages_professeurs_ibfk_4` FOREIGN KEY (`id_cours`) REFERENCES `cours` (`id_cours`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages_professeurs`
--

LOCK TABLES `messages_professeurs` WRITE;
/*!40000 ALTER TABLE `messages_professeurs` DISABLE KEYS */;
/*!40000 ALTER TABLE `messages_professeurs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notes`
--

DROP TABLE IF EXISTS `notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notes` (
  `id_note` int(11) NOT NULL AUTO_INCREMENT,
  `id_etudiant` int(11) DEFAULT NULL,
  `id_cours` int(11) DEFAULT NULL,
  `note` decimal(5,2) DEFAULT NULL,
  `session` enum('1ère Session','2ème Session') DEFAULT NULL,
  `date_saisie` date DEFAULT NULL,
  `statut` enum('brouillon','soumis','valide','rejete','a_corriger') DEFAULT 'brouillon',
  `date_soumission` datetime DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL,
  `motif_rejet` text DEFAULT NULL,
  `id_doyen` int(11) DEFAULT NULL,
  `date_publication` datetime DEFAULT NULL,
  PRIMARY KEY (`id_note`),
  KEY `id_etudiant` (`id_etudiant`),
  KEY `id_cours` (`id_cours`),
  KEY `id_doyen` (`id_doyen`),
  CONSTRAINT `fk_notes_cours` FOREIGN KEY (`id_cours`) REFERENCES `cours` (`id_cours`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notes_etudiants` FOREIGN KEY (`id_etudiant`) REFERENCES `etudiants` (`id_etudiant`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`id_doyen`) REFERENCES `users` (`id_user`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notes`
--

LOCK TABLES `notes` WRITE;
/*!40000 ALTER TABLE `notes` DISABLE KEYS */;
INSERT INTO `notes` VALUES (17,4,14,17.50,'','2026-10-29','brouillon',NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `notes` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_notif_insert_note AFTER INSERT ON notes
FOR EACH ROW
BEGIN
    DECLARE v_nom_etudiant VARCHAR(100);
    DECLARE v_nom_cours VARCHAR(150);
    DECLARE v_id_dept INT;
    
    SELECT u.nom INTO v_nom_etudiant FROM etudiants e JOIN users u ON e.id_user = u.id_user WHERE e.id_etudiant = NEW.id_etudiant;
    SELECT nom_cours INTO v_nom_cours FROM cours WHERE id_cours = NEW.id_cours;
    SELECT c.id_dept INTO v_id_dept FROM etudiants e JOIN classes c ON e.id_classe = c.id_classe WHERE e.id_etudiant = NEW.id_etudiant;
    
    INSERT INTO notifications (source_table, id_dept, type_evenement, titre, message, statut, date_creation)
    VALUES ('notes', v_id_dept, 'SUCCES',
        'Nouvelle note attribuée',
        CONCAT('L''étudiant ', UPPER(v_nom_etudiant), ' a reçu la note de ', NEW.note, ' dans le cours "', v_nom_cours, '"'),
        'non_lu', NOW());
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_notif_update_note` AFTER UPDATE ON `notes`
 FOR EACH ROW 
BEGIN
    DECLARE v_nom_etudiant VARCHAR(100);
    DECLARE v_nom_cours VARCHAR(150);
    
    SELECT u.nom INTO v_nom_etudiant 
    FROM etudiants e 
    JOIN users u ON e.id_user = u.id_user 
    WHERE e.id_etudiant = NEW.id_etudiant;

    SELECT nom_cours INTO v_nom_cours 
    FROM cours 
    WHERE id_cours = NEW.id_cours;

    -- Se déclenche si la note ou la session change
    IF (OLD.note <> NEW.note OR OLD.session <> NEW.session) THEN
        INSERT INTO notifications (titre, message, type_evenement, statut, date_creation)
        VALUES (
            CONCAT('Note modifiée : ', v_nom_etudiant),
            CONCAT('La note de ', v_nom_etudiant, ' pour le cours "', v_nom_cours, '" a été modifiée. ',
                   'Ancienne note : ', OLD.note, ' -> Nouvelle note : ', NEW.note, ' (Session : ', NEW.session, ').'),
            'ALERT', -- Badge Orange
            'non_lu',
            NOW()
        );
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_notif_delete_note` BEFORE DELETE ON `notes`
 FOR EACH ROW 
BEGIN
    DECLARE v_nom_etudiant VARCHAR(100);
    DECLARE v_nom_cours VARCHAR(150);
    
    SELECT u.nom INTO v_nom_etudiant 
    FROM etudiants e 
    JOIN users u ON e.id_user = u.id_user 
    WHERE e.id_etudiant = OLD.id_etudiant;

    SELECT nom_cours INTO v_nom_cours 
    FROM cours 
    WHERE id_cours = OLD.id_cours;

    INSERT INTO notifications (titre, message, type_evenement, statut, date_creation)
    VALUES (
        CONCAT('Note supprimée'),
        CONCAT('ATTENTION : La note de ', OLD.note, ' obtenue par ', v_nom_etudiant, ' dans le cours "', v_nom_cours, '" (Session : ', OLD.session, ') a été supprimée des registres.'),
        'URGENT', -- Badge Rouge
        'non_lu',
        NOW()
    );
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id_notification` int(11) NOT NULL AUTO_INCREMENT,
  `source_table` varchar(50) NOT NULL,
  `id_dept` int(11) DEFAULT NULL,
  `type_evenement` enum('INFO','SUCCES','ALERT','URGENT','INSERT','UPDATE','DELETE') NOT NULL DEFAULT 'INFO',
  `titre` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `statut` enum('non_lu','lu') DEFAULT 'non_lu',
  `date_creation` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_notification`),
  KEY `idx_dept` (`id_dept`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,'etudiants',NULL,'SUCCES','Nouvelle inscription : MIZERO kersaint','L\'étudiant MIZERO kersaint (Matricule : FSGE-2026-5998@UNIV) vient d\'être inscrit avec succès dans la classe BAC III.','non_lu','2026-07-07 14:53:16'),(2,'etudiants',NULL,'SUCCES','Nouvelle inscription : KAZE diella','L\'étudiant KAZE diella (Matricule : FSGE-2026-1211@UNIV) vient d\'être inscrit avec succès dans la classe BAC I.','non_lu','2026-07-07 14:56:21'),(3,'departements',NULL,'INFO','Nouveau Département : FSI','Le département universitaire \"FSI\" a été officiellement créé dans le système.','non_lu','2026-07-07 16:36:09'),(4,'departements',NULL,'INFO','Nouveau Département : SPRI','Le département universitaire \"SPRI\" a été officiellement créé dans le système.','non_lu','2026-07-07 16:45:13'),(5,'departements',NULL,'INFO','Nouveau Département : Genie Civil','Le département universitaire \"Genie Civil\" a été officiellement créé dans le système.','non_lu','2026-10-06 16:16:22'),(6,'departements',NULL,'INFO','Nouveau Département : Batiment','Le département universitaire \"Batiment\" a été officiellement créé dans le système.','non_lu','2026-10-06 17:00:01'),(7,'departements',NULL,'INFO','Nouveau Département : gh','Le département universitaire \"gh\" a été officiellement créé dans le système.','non_lu','2026-10-06 18:26:06'),(8,'classes',NULL,'SUCCES','Nouvelle classe créée : BAC V','La classe \"BAC V\" a été ouverte pour le département Genie Civil.','non_lu','2026-10-06 18:28:52'),(9,'classes',NULL,'ALERT','Classe modifiée : BAC 5','La classe \"BAC 5\" a été renommée ou réaffectée en \"BAC 5\".','non_lu','2026-10-06 18:40:56'),(10,'classes',NULL,'ALERT','Classe modifiée : BAC 5','La classe \"BAC 5\" a été renommée ou réaffectée en \"BAC 6\".','non_lu','2026-10-06 18:45:47'),(11,'departements',NULL,'URGENT','Département supprimé : gh','Le département universitaire \"gh\" a été retiré de la base de données avec succès.','non_lu','2026-10-10 15:17:03'),(12,'departements',NULL,'INFO','Nouveau Département : droit cr','Le département universitaire \"droit cr\" a été officiellement créé dans le système.','non_lu','2026-10-12 17:25:29'),(13,'classes',NULL,'SUCCES','Nouvelle classe créée : BAC V','La classe \"BAC V\" a été ouverte pour le département droit cr.','non_lu','2026-10-12 17:25:59'),(14,'classes',NULL,'ALERT','Classe modifiée : BAC I','La classe \"BAC I\" a été renommée ou réaffectée en \"BAC II\".','non_lu','2026-10-12 17:46:36'),(15,'professeurs',NULL,'SUCCES','Nouveau professeur : BIGIRIMANA','L\'enseignant BIGIRIMANA (Spécialité : Non renseignée, Diplôme : Non renseigné) a été enregistré dans le corps professoral.','non_lu','2026-10-26 13:54:04'),(16,'cours',NULL,'SUCCES','Nouveau cours : Cloud Computing','Le cours de \"Cloud Computing\" (Catégorie: En attente, Crédits: 4, Semestre: Semestre 2) a été ajouté aux programmes.','non_lu','2026-10-26 14:13:34'),(17,'cours',NULL,'SUCCES','Nouveau cours : Intelligence Artificielle','Le cours de \"Intelligence Artificielle\" (Catégorie: En attente, Crédits: 4, Semestre: Semestre 1) a été ajouté aux programmes.','non_lu','2026-10-26 14:13:34'),(18,'etudiants',NULL,'ALERT','Mise à jour : MIZERO francis','Les informations de l\'étudiant MIZERO francis (Matricule : ) ont été modifiées.','non_lu','2026-10-26 14:47:59'),(19,'etudiants',NULL,'ALERT','Mise à jour : MIZERO francis','Les informations de l\'étudiant MIZERO francis (Matricule : ) ont été modifiées.','non_lu','2026-10-26 15:06:24'),(20,'etudiants',NULL,'ALERT','Mise à jour : MIZERO francis','Les informations de l\'étudiant MIZERO francis (Matricule : ) ont été modifiées.','non_lu','2026-10-26 15:11:23'),(21,'etudiants',NULL,'ALERT','Mise à jour : MIZERO francis','Les informations de l\'étudiant MIZERO francis (Matricule : ) ont été modifiées.','non_lu','2026-10-26 15:23:02'),(22,'etudiants',NULL,'SUCCES','Nouvelle inscription : KARORERO Happy Noé','L\'étudiant KARORERO Happy Noé (Matricule : INFO-2026-1987@UNIV) vient d\'être inscrit avec succès dans la classe BAC I.','non_lu','2026-10-26 16:36:45'),(23,'message',NULL,'INFO','???? Nouveau message','Le professeur BIGIRIMANA Marc vous a envoyé un message : Examen','non_lu','2026-10-27 12:35:53'),(25,'departements',19,'INFO','Nouveau Département : Bâtiments et Infrastructures','Le département \"Bâtiments et Infrastructures\" a été créé.','non_lu','2026-10-28 17:14:10'),(26,'departements',20,'INFO','Nouveau Département : Génie Hydraulique','Le département \"Génie Hydraulique\" a été créé.','non_lu','2026-10-28 17:14:10'),(27,'departements',21,'INFO','Nouveau Département : Génie électrique','Le département \"Génie électrique\" a été créé.','non_lu','2026-10-28 17:14:10'),(28,'departements',22,'INFO','Nouveau Département : Génie Mécanique et énergétique','Le département \"Génie Mécanique et énergétique\" a été créé.','non_lu','2026-10-28 17:14:10'),(29,'departements',23,'INFO','Nouveau Département : Aménagement du Territoire','Le département \"Aménagement du Territoire\" a été créé.','non_lu','2026-10-28 17:14:10'),(30,'departements',24,'INFO','Nouveau Département : Urbanisme et Habitat','Le département \"Urbanisme et Habitat\" a été créé.','non_lu','2026-10-28 17:14:10'),(31,'departements',25,'INFO','Nouveau Département : Science Politique et Relations Internationales','Le département \"Science Politique et Relations Internationales\" a été créé.','non_lu','2026-10-28 17:14:10'),(32,'departements',26,'INFO','Nouveau Département : Sociologie','Le département \"Sociologie\" a été créé.','non_lu','2026-10-28 17:14:10'),(33,'departements',27,'INFO','Nouveau Département : Communication organisationnelle','Le département \"Communication organisationnelle\" a été créé.','non_lu','2026-10-28 17:14:10'),(34,'departements',28,'INFO','Nouveau Département : Mass médias','Le département \"Mass médias\" a été créé.','non_lu','2026-10-28 17:14:10'),(35,'departements',29,'INFO','Nouveau Département : Économie et commerce international','Le département \"Économie et commerce international\" a été créé.','lu','2026-10-28 17:14:10'),(36,'departements',30,'INFO','Nouveau Département : Économie des ressources naturelles et de l’environnement','Le département \"Économie des ressources naturelles et de l’environnement\" a été créé.','non_lu','2026-10-28 17:14:10'),(37,'departements',31,'INFO','Nouveau Département : Économie agricole','Le département \"Économie agricole\" a été créé.','non_lu','2026-10-28 17:14:10'),(38,'departements',32,'INFO','Nouveau Département : Audit et contrôle de gestion','Le département \"Audit et contrôle de gestion\" a été créé.','non_lu','2026-10-28 17:14:10'),(39,'departements',33,'INFO','Nouveau Département : Marketing et management','Le département \"Marketing et management\" a été créé.','non_lu','2026-10-28 17:14:10'),(40,'departements',34,'INFO','Nouveau Département : Finance et comptabilité','Le département \"Finance et comptabilité\" a été créé.','non_lu','2026-10-28 17:14:10'),(41,'departements',35,'INFO','Nouveau Département : Génie Logiciel','Le département \"Génie Logiciel\" a été créé.','non_lu','2026-10-28 17:14:10'),(42,'departements',36,'INFO','Nouveau Département : Ingénierie réseaux','Le département \"Ingénierie réseaux\" a été créé.','non_lu','2026-10-28 17:14:10'),(43,'departements',37,'INFO','Nouveau Département : Informatique de Gestion','Le département \"Informatique de Gestion\" a été créé.','non_lu','2026-10-28 17:14:10'),(44,'departements',38,'INFO','Nouveau Département : Génie informatique','Le département \"Génie informatique\" a été créé.','non_lu','2026-10-28 17:14:10'),(45,'departements',39,'INFO','Nouveau Département : Télécommunication et Réseaux','Le département \"Télécommunication et Réseaux\" a été créé.','non_lu','2026-10-28 17:14:10'),(46,'departements',NULL,'URGENT','Département supprimé : Droit','Le département universitaire \"Droit\" a été retiré de la base de données avec succès.','non_lu','2026-10-28 17:17:22'),(47,'departements',NULL,'URGENT','Département supprimé : M?decine','Le département universitaire \"M?decine\" a été retiré de la base de données avec succès.','non_lu','2026-10-28 17:17:22'),(48,'departements',NULL,'URGENT','Département supprimé : Gestion','Le département universitaire \"Gestion\" a été retiré de la base de données avec succès.','non_lu','2026-10-28 17:17:22'),(49,'departements',NULL,'ALERT','Département modifié : droit cr','Le département \"droit cr\" a été renommé en \"Droit International\".','non_lu','2026-10-29 13:34:43'),(50,'notes',1,'SUCCES','Nouvelle note attribuée','L\'étudiant KARORERO a reçu la note de 17.50 dans le cours \"Cloud Computing\"','non_lu','2026-10-29 13:39:03');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_notifications_fill_source`
BEFORE INSERT ON `notifications`
FOR EACH ROW
BEGIN
    -- Ne remplit que si source_table est vide
    IF NEW.source_table IS NULL OR NEW.source_table = '' THEN
        
        -- Départements
        IF NEW.titre LIKE 'Nouveau Département%' 
        OR NEW.titre LIKE '%Département supprimé%' 
        OR NEW.titre LIKE '%Département modifié%' THEN
            SET NEW.source_table = 'departements';
        
        -- Classes
        ELSEIF NEW.titre LIKE 'Nouvelle classe%' 
        OR     NEW.titre LIKE '%Classe modifiée%' 
        OR     NEW.titre LIKE '%Classe supprimée%' THEN
            SET NEW.source_table = 'classes';
        
        -- Cours
        ELSEIF NEW.titre LIKE 'Nouveau cours%' 
        OR     NEW.titre LIKE '%Cours modifié%' 
        OR     NEW.titre LIKE '%Cours supprimé%' THEN
            SET NEW.source_table = 'cours';
        
        -- Professeurs
        ELSEIF NEW.titre LIKE 'Nouveau professeur%' 
        OR     NEW.titre LIKE '%Profil prof%' 
        OR     NEW.titre LIKE '%Professeur retiré%' THEN
            SET NEW.source_table = 'professeurs';
        
        -- Étudiants / inscriptions
        ELSEIF NEW.titre LIKE 'Nouvelle inscription%' 
        OR     NEW.titre LIKE 'Mise à jour : %' THEN
            SET NEW.source_table = 'etudiants';
        
        -- Notes
        ELSEIF NEW.titre LIKE 'Nouvelle note%' 
        OR     NEW.titre LIKE '%Note modifiée%' 
        OR     NEW.titre LIKE '%Note supprimée%' THEN
            SET NEW.source_table = 'notes';
        
        -- Paiements
        ELSEIF NEW.titre LIKE 'Paiement reçu%' 
        OR     NEW.titre LIKE '%Paiement modifié%' 
        OR     NEW.titre LIKE '%Paiement SUPPRIMÉ%' THEN
            SET NEW.source_table = 'paiements';
        
        -- Par défaut
        ELSE
            SET NEW.source_table = 'systeme';
        END IF;
    
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `notifications_backup_avant_fix`
--

DROP TABLE IF EXISTS `notifications_backup_avant_fix`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications_backup_avant_fix` (
  `id_notification` int(11) NOT NULL DEFAULT 0,
  `source_table` varchar(50) NOT NULL,
  `type_evenement` enum('INSERT','UPDATE','DELETE') NOT NULL,
  `titre` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `statut` enum('non_lu','lu') DEFAULT 'non_lu',
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications_backup_avant_fix`
--

LOCK TABLES `notifications_backup_avant_fix` WRITE;
/*!40000 ALTER TABLE `notifications_backup_avant_fix` DISABLE KEYS */;
INSERT INTO `notifications_backup_avant_fix` VALUES (1,'','','Nouvelle inscription : MIZERO kersaint','L\'étudiant MIZERO kersaint (Matricule : FSGE-2026-5998@UNIV) vient d\'être inscrit avec succès dans la classe BAC III.','non_lu','2026-07-07 14:53:16'),(2,'','','Nouvelle inscription : KAZE diella','L\'étudiant KAZE diella (Matricule : FSGE-2026-1211@UNIV) vient d\'être inscrit avec succès dans la classe BAC I.','non_lu','2026-07-07 14:56:21'),(3,'','','Nouveau Département : FSI','Le département universitaire \"FSI\" a été officiellement créé dans le système.','non_lu','2026-07-07 16:36:09'),(4,'','','Nouveau Département : SPRI','Le département universitaire \"SPRI\" a été officiellement créé dans le système.','non_lu','2026-07-07 16:45:13'),(5,'','','Nouveau Département : Genie Civil','Le département universitaire \"Genie Civil\" a été officiellement créé dans le système.','non_lu','2026-10-06 16:16:22'),(6,'','','Nouveau Département : Batiment','Le département universitaire \"Batiment\" a été officiellement créé dans le système.','non_lu','2026-10-06 17:00:01'),(7,'','','Nouveau Département : gh','Le département universitaire \"gh\" a été officiellement créé dans le système.','non_lu','2026-10-06 18:26:06'),(8,'','','Nouvelle classe créée : BAC V','La classe \"BAC V\" a été ouverte pour le département Genie Civil.','non_lu','2026-10-06 18:28:52'),(9,'','','Classe modifiée : BAC 5','La classe \"BAC 5\" a été renommée ou réaffectée en \"BAC 5\".','non_lu','2026-10-06 18:40:56'),(10,'','','Classe modifiée : BAC 5','La classe \"BAC 5\" a été renommée ou réaffectée en \"BAC 6\".','non_lu','2026-10-06 18:45:47'),(11,'','','Département supprimé : gh','Le département universitaire \"gh\" a été retiré de la base de données avec succès.','non_lu','2026-10-10 15:17:03'),(12,'','','Nouveau Département : droit cr','Le département universitaire \"droit cr\" a été officiellement créé dans le système.','non_lu','2026-10-12 17:25:29'),(13,'','','Nouvelle classe créée : BAC V','La classe \"BAC V\" a été ouverte pour le département droit cr.','non_lu','2026-10-12 17:25:59'),(14,'','','Classe modifiée : BAC I','La classe \"BAC I\" a été renommée ou réaffectée en \"BAC II\".','non_lu','2026-10-12 17:46:36');
/*!40000 ALTER TABLE `notifications_backup_avant_fix` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `paiements`
--

DROP TABLE IF EXISTS `paiements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `paiements` (
  `id_paiement` int(11) NOT NULL AUTO_INCREMENT,
  `id_etudiant` int(11) NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `date_paiement` date NOT NULL,
  `banque` varchar(50) DEFAULT NULL,
  `annee_academique` varchar(9) DEFAULT NULL,
  `tranche` varchar(20) DEFAULT NULL,
  `motif` varchar(255) DEFAULT NULL,
  `bordereau` varchar(20) DEFAULT NULL,
  `statut` enum('en_attente','valide','rejete') DEFAULT 'en_attente',
  `motif_rejet` varchar(255) DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL,
  `valide_par` int(11) DEFAULT NULL,
  `numero_recu` varchar(50) DEFAULT NULL,
  `date_recu` datetime DEFAULT NULL,
  PRIMARY KEY (`id_paiement`),
  KEY `id_etudiant` (`id_etudiant`),
  KEY `valide_par` (`valide_par`),
  CONSTRAINT `fk_paiements_etudiants` FOREIGN KEY (`id_etudiant`) REFERENCES `etudiants` (`id_etudiant`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `paiements_ibfk_1` FOREIGN KEY (`valide_par`) REFERENCES `users` (`id_user`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `paiements`
--

LOCK TABLES `paiements` WRITE;
/*!40000 ALTER TABLE `paiements` DISABLE KEYS */;
INSERT INTO `paiements` VALUES (5,5,150000.00,'2024-02-14','Cash',NULL,NULL,NULL,NULL,'en_attente',NULL,NULL,NULL,NULL,NULL),(6,6,200000.00,'2024-02-15','Mobile',NULL,NULL,NULL,NULL,'en_attente',NULL,NULL,NULL,NULL,NULL),(7,7,150000.00,'2024-02-16','Banque',NULL,NULL,NULL,NULL,'en_attente',NULL,NULL,NULL,NULL,NULL),(8,8,220000.00,'2024-02-17','Mobile',NULL,NULL,NULL,NULL,'en_attente',NULL,NULL,NULL,NULL,NULL),(9,9,170000.00,'2024-02-18','Cash',NULL,NULL,NULL,NULL,'en_attente',NULL,NULL,NULL,NULL,NULL),(10,10,160000.00,'2024-02-19','Banque',NULL,NULL,NULL,NULL,'en_attente',NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `paiements` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_notif_insert_paiement AFTER INSERT ON paiements
FOR EACH ROW
BEGIN
    DECLARE v_nom_etudiant VARCHAR(100);
    DECLARE v_id_dept INT;
    
    SELECT u.nom INTO v_nom_etudiant FROM etudiants e JOIN users u ON e.id_user = u.id_user WHERE e.id_etudiant = NEW.id_etudiant;
    SELECT c.id_dept INTO v_id_dept FROM etudiants e JOIN classes c ON e.id_classe = c.id_classe WHERE e.id_etudiant = NEW.id_etudiant;
    
    INSERT INTO notifications (source_table, id_dept, type_evenement, titre, message, statut, date_creation)
    VALUES ('paiements', v_id_dept, 'SUCCES',
        CONCAT('Paiement reçu : ', NEW.montant, ' BIF'),
        CONCAT('Un versement de ', NEW.montant, ' BIF a été enregistré pour ', UPPER(v_nom_etudiant)),
        'non_lu', NOW());
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_notif_update_paiement` AFTER UPDATE ON `paiements`
 FOR EACH ROW 
BEGIN
    DECLARE v_nom_etudiant VARCHAR(100);
    
    SELECT u.nom INTO v_nom_etudiant 
    FROM etudiants e 
    JOIN users u ON e.id_user = u.id_user 
    WHERE e.id_etudiant = NEW.id_etudiant;

    -- Se déclenche si le montant, la banque, la tranche ou le bordereau change
    IF (OLD.montant <> NEW.montant OR OLD.banque <> NEW.banque OR OLD.tranche <> NEW.tranche OR OLD.bordereau <> NEW.bordereau) THEN
        INSERT INTO notifications (titre, message, type_evenement, statut, date_creation)
        VALUES (
            CONCAT('Paiement modifié : ', v_nom_etudiant),
            CONCAT('Le paiement de ', v_nom_etudiant, ' a été modifié. ',
                   'Ancien montant : ', OLD.montant, ' BIF -> Nouveau montant : ', NEW.montant, ' BIF. ',
                   'Nouvelle tranche : ', NEW.tranche, ', Bordereau : ', NEW.bordereau, '.'),
            'ALERT',
            'non_lu',
            NOW()
        );
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_notif_delete_paiement` BEFORE DELETE ON `paiements`
 FOR EACH ROW 
BEGIN
    DECLARE v_nom_etudiant VARCHAR(100);
    
    SELECT u.nom INTO v_nom_etudiant 
    FROM etudiants e 
    JOIN users u ON e.id_user = u.id_user 
    WHERE e.id_etudiant = OLD.id_etudiant;

    INSERT INTO notifications (titre, message, type_evenement, statut, date_creation)
    VALUES (
        CONCAT('❌ Paiement SUPPRIMÉ : ', v_nom_etudiant),
        CONCAT('ATTENTION : Le reçu de paiement de ', OLD.montant, ' BIF pour l\'étudiant ', v_nom_etudiant, ' (Tranche : ', OLD.tranche, ', Bordereau : ', OLD.bordereau, ') a été supprimé des registres.'),
        'URGENT',
        'non_lu',
        NOW()
    );
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id_reset` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `utilise` tinyint(1) NOT NULL DEFAULT 0,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `adresse_ip` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id_reset`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `idx_user` (`id_user`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `presences`
--

DROP TABLE IF EXISTS `presences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `presences` (
  `id_presence` int(11) NOT NULL AUTO_INCREMENT,
  `id_etudiant` int(11) NOT NULL,
  `id_cours` int(11) NOT NULL,
  `date_seance` date NOT NULL,
  `statut` enum('present','absent','retard','justifie') DEFAULT 'present',
  PRIMARY KEY (`id_presence`),
  UNIQUE KEY `id_etudiant` (`id_etudiant`,`id_cours`,`date_seance`),
  KEY `id_cours` (`id_cours`),
  CONSTRAINT `presences_ibfk_1` FOREIGN KEY (`id_etudiant`) REFERENCES `etudiants` (`id_etudiant`) ON DELETE CASCADE,
  CONSTRAINT `presences_ibfk_2` FOREIGN KEY (`id_cours`) REFERENCES `cours` (`id_cours`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `presences`
--

LOCK TABLES `presences` WRITE;
/*!40000 ALTER TABLE `presences` DISABLE KEYS */;
/*!40000 ALTER TABLE `presences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `professeurs`
--

DROP TABLE IF EXISTS `professeurs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `professeurs` (
  `id_prof` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `specialite` varchar(150) DEFAULT NULL,
  `diplome` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_prof`),
  KEY `id_user` (`id_user`),
  CONSTRAINT `professeurs_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `professeurs`
--

LOCK TABLES `professeurs` WRITE;
/*!40000 ALTER TABLE `professeurs` DISABLE KEYS */;
INSERT INTO `professeurs` VALUES (3,12,'Non renseignée','Non renseigné');
/*!40000 ALTER TABLE `professeurs` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_notif_insert_prof` AFTER INSERT ON `professeurs`
FOR EACH ROW
BEGIN
    DECLARE v_nom_prof VARCHAR(100);
    
    SELECT COALESCE(nom, 'Inconnu') INTO v_nom_prof 
    FROM users WHERE id_user = NEW.id_user;

    INSERT INTO notifications (titre, message, type_evenement, statut, date_creation)
    VALUES (
        CONCAT('Nouveau professeur : ', COALESCE(v_nom_prof, 'Inconnu')),
        CONCAT('L''enseignant ', UPPER(COALESCE(v_nom_prof, 'INCONNU')), 
               ' (Spécialité : ', COALESCE(NEW.specialite, 'Non renseignée'), 
               ', Diplôme : ', COALESCE(NEW.diplome, 'Non renseigné'), 
               ') a été enregistré dans le corps professoral.'),
        'SUCCES',
        'non_lu',
        NOW()
    );
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_notif_update_prof` AFTER UPDATE ON `professeurs`
FOR EACH ROW
BEGIN
    DECLARE v_nom_prof VARCHAR(100);
    
    SELECT COALESCE(nom, 'Inconnu') INTO v_nom_prof 
    FROM users WHERE id_user = NEW.id_user;

    IF (COALESCE(OLD.specialite, '') <> COALESCE(NEW.specialite, '') 
        OR COALESCE(OLD.diplome, '') <> COALESCE(NEW.diplome, '')) THEN
        INSERT INTO notifications (titre, message, type_evenement, statut, date_creation)
        VALUES (
            CONCAT('Profil prof modifié : ', COALESCE(v_nom_prof, 'Inconnu')),
            CONCAT('Le profil de ', COALESCE(v_nom_prof, 'Inconnu'), ' a été mis à jour. ',
                   'Nouvelle spécialité : ', COALESCE(NEW.specialite, 'Non renseignée'), 
                   ', Nouveau diplôme : ', COALESCE(NEW.diplome, 'Non renseigné'), '.'),
            'ALERT',
            'non_lu',
            NOW()
        );
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_notif_delete_prof` BEFORE DELETE ON `professeurs`
FOR EACH ROW
BEGIN
    DECLARE v_nom_prof VARCHAR(100);
    
    SELECT COALESCE(nom, 'Inconnu') INTO v_nom_prof 
    FROM users WHERE id_user = OLD.id_user;

    INSERT INTO notifications (titre, message, type_evenement, statut, date_creation)
    VALUES (
        CONCAT('Professeur retiré : ', COALESCE(v_nom_prof, 'Inconnu')),
        CONCAT('L''enseignant ', COALESCE(v_nom_prof, 'Inconnu'), 
               ' (Spécialité : ', COALESCE(OLD.specialite, 'Non renseignée'), 
               ') a été retiré de la liste des professeurs.'),
        'URGENT',
        'non_lu',
        NOW()
    );
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `resultats_annuels`
--

DROP TABLE IF EXISTS `resultats_annuels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resultats_annuels` (
  `id_resultat` int(11) NOT NULL AUTO_INCREMENT,
  `id_etudiant` int(11) NOT NULL,
  `annee_academique` varchar(9) NOT NULL,
  `moyenne` decimal(5,2) DEFAULT NULL,
  `credits_obtenus` int(11) DEFAULT 0,
  `credits_requis` int(11) DEFAULT 0,
  `decision` enum('ADMIS','AJOURNE','REDOUBLANT','EN_ATTENTE') DEFAULT 'EN_ATTENTE',
  `statut` enum('en_attente','valide','rejete') DEFAULT 'en_attente',
  `date_validation` datetime DEFAULT NULL,
  `valide_par` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_resultat`),
  UNIQUE KEY `unique_etudiant_annee` (`id_etudiant`,`annee_academique`),
  KEY `valide_par` (`valide_par`),
  CONSTRAINT `resultats_annuels_ibfk_1` FOREIGN KEY (`id_etudiant`) REFERENCES `etudiants` (`id_etudiant`) ON DELETE CASCADE,
  CONSTRAINT `resultats_annuels_ibfk_2` FOREIGN KEY (`valide_par`) REFERENCES `users` (`id_user`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resultats_annuels`
--

LOCK TABLES `resultats_annuels` WRITE;
/*!40000 ALTER TABLE `resultats_annuels` DISABLE KEYS */;
/*!40000 ALTER TABLE `resultats_annuels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supports_cours`
--

DROP TABLE IF EXISTS `supports_cours`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supports_cours` (
  `id_support` int(11) NOT NULL AUTO_INCREMENT,
  `id_cours` int(11) NOT NULL,
  `id_prof` int(11) NOT NULL,
  `id_classe` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `fichier` varchar(255) NOT NULL,
  `type_fichier` varchar(50) DEFAULT NULL,
  `date_upload` datetime DEFAULT current_timestamp(),
  `semestre` varchar(20) DEFAULT NULL,
  `annee_academique` varchar(9) DEFAULT NULL,
  `version` int(11) DEFAULT 1,
  `share_token` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id_support`),
  KEY `id_cours` (`id_cours`),
  KEY `id_prof` (`id_prof`),
  KEY `id_classe` (`id_classe`),
  CONSTRAINT `supports_cours_ibfk_1` FOREIGN KEY (`id_cours`) REFERENCES `cours` (`id_cours`) ON DELETE CASCADE,
  CONSTRAINT `supports_cours_ibfk_2` FOREIGN KEY (`id_prof`) REFERENCES `professeurs` (`id_prof`) ON DELETE CASCADE,
  CONSTRAINT `supports_cours_ibfk_3` FOREIGN KEY (`id_classe`) REFERENCES `classes` (`id_classe`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supports_cours`
--

LOCK TABLES `supports_cours` WRITE;
/*!40000 ALTER TABLE `supports_cours` DISABLE KEYS */;
INSERT INTO `supports_cours` VALUES (1,14,3,1,'Introduction','introduction','support_20261026_141513_fe8d97da.docx','docx','2026-10-26 14:15:13','Semestre 2','2026-2027',1,NULL);
/*!40000 ALTER TABLE `supports_cours` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_sessions`
--

DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_sessions` (
  `id_session` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) DEFAULT NULL,
  `statut` enum('Connecte','Deconnecte') DEFAULT 'Connecte',
  `adresse_ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `date_connexion` datetime DEFAULT NULL,
  `date_deconnexion` datetime DEFAULT NULL,
  `derniere_activite` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_session`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_sessions`
--

LOCK TABLES `user_sessions` WRITE;
/*!40000 ALTER TABLE `user_sessions` DISABLE KEYS */;
INSERT INTO `user_sessions` VALUES (1,3,'Deconnecte','192.168.1.42','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36','2026-10-16 16:49:23',NULL,'2026-10-20 15:43:45'),(2,3,'Deconnecte','192.168.1.42','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36','2026-10-16 16:49:23',NULL,'2026-10-20 15:43:45');
/*!40000 ALTER TABLE `user_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_sessions_backup_avant_extension`
--

DROP TABLE IF EXISTS `user_sessions_backup_avant_extension`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_sessions_backup_avant_extension` (
  `id_session` int(11) NOT NULL DEFAULT 0,
  `id_user` int(11) DEFAULT NULL,
  `statut` enum('Connecte','Deconnecte') DEFAULT 'Connecte',
  `derniere_activite` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_sessions_backup_avant_extension`
--

LOCK TABLES `user_sessions_backup_avant_extension` WRITE;
/*!40000 ALTER TABLE `user_sessions_backup_avant_extension` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_sessions_backup_avant_extension` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id_user` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) DEFAULT NULL,
  `prenom` varchar(100) DEFAULT NULL,
  `sexe` enum('Masculin','Feminin') DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `email_auth` varchar(150) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `role` enum('Administrateur','Professeur','Comptable','Etudiant','Lecteur','Doyen','Recteur','Agent') NOT NULL,
  `statut` enum('Actif','Inactif','Suspendu') DEFAULT 'Actif',
  `photo` varchar(255) DEFAULT 'default.png',
  `premiere_connexion` enum('Oui','Non') DEFAULT 'Oui',
  `tentative_connexion` int(11) DEFAULT 0,
  `date_blocage` datetime DEFAULT NULL,
  `date_modification_mdp` datetime DEFAULT NULL,
  `derniere_connexion` datetime DEFAULT NULL,
  `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
  `derniere_activite` datetime DEFAULT NULL COMMENT 'Dernière activité authentifiée',
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email_auth` (`email_auth`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (3,'mizero','francis','Masculin','+25764346117','mizero100@gmail.com','fmizero@etu.univ.local','f.mizero','$2y$10$WD.O2DhydBXx8biIrJYaVu/E726y9DFkQzxMe.xbElvVf0kdm2Dg6','Etudiant','Actif','profile_3.png','Non',0,NULL,'2026-10-26 14:25:49','2026-10-26 14:25:27','2026-07-07 12:14:06',NULL),(5,'mizero','kersaint','Masculin','+25766012083','mizero100@gmail.com','kmizero@etu.univ.local','k.mizero','$2y$10$kKzmjaq6kbGDTjvvxwWYA.wJD23dO57TVe6ppIWZgaMdOadP28/Iu','Etudiant','Actif','default.png','Non',0,NULL,'2026-10-26 14:18:46','2026-10-26 14:18:22','2026-07-07 12:53:16',NULL),(6,'kaze','diella','Feminin','+25779898989','didi@gmail.com','dkaze@etu.univ.local','d.kaze','$2y$10$vxgoTRsCgkvalC2AvM2Pze6ZMV8mEMFPwymb8gdtktF3AoqPIcUHC','Etudiant','Actif','default.png','Oui',0,NULL,NULL,NULL,'2026-07-07 12:56:21',NULL),(7,'bn','ef','Masculin','+25700000000','test.user@example.com','ebn@lec.univ.local','t.user','$2y$10$coiMFlYaAN6YrQQOK59d4eC43zymLgkDOaXRnjF3FyoFoOyr3cnqq','Recteur','Actif','default.png','Oui',0,NULL,NULL,NULL,'2026-10-13 12:58:39',NULL),(8,'Mizero','Karel','Masculin','+25764346117','mizerofk@gmail.com','kmizero@adm.univ.local','Mizerok','$2y$10$X8DI5WIhltnvB.2OVddbyuItr3F0YV79Dahqob2uIRFDGywrCPxYq','Administrateur','Actif','profile_8.png','Non',0,NULL,'2026-10-26 12:33:42','2026-10-30 15:26:28','2026-10-20 15:20:17',NULL),(9,'Niyomwungere','Olyne','Feminin','+25764346117','mizerofk@gmail.com','oniyomwungere@doy.univ.local','Niyomwungere','$2y$10$c.WwdgYf344OuPmje619I.kbQsbIL/Zh2UF8BvsiliaxlJxZcAVnm','Doyen','Actif','default.png','Non',0,NULL,'2026-10-24 17:24:16','2026-10-30 13:42:54','2026-10-20 15:48:39',NULL),(12,'BIGIRIMANA','Marc','Masculin','+25764346117','mizero100@gmail.com','mbigirimana@prof.univ.local','BIGMARC','$2y$10$vU1mBRfKOZTLcM4udkUvnOzO3ooQU40y9ktgGKV/aFxltkDy6XCr2','Professeur','Actif','default.png','Non',0,NULL,'2026-10-26 14:05:16','2026-10-30 14:17:41','2026-10-26 12:54:04',NULL),(13,'KARORERO','Happy Noé','Masculin','64346117','mizero100@gmail.com','hkarorero@etu.univ.local','h.karorero','$2y$10$1Op5054CEoMqHSUCSAEFk.bNe.KotbFFslD8EtsrCQcVVMP9TfDFm','Etudiant','Actif','default.png','Oui',0,NULL,NULL,NULL,'2026-10-26 15:36:45',NULL),(19,'KEZA','Micky','Feminin','+25764346117','mizero100@gmail.com','mkeza1@ins.univ.local','KEZAMICKY','$2y$10$nmZwvK6l3TfzZDMmuQZMW.CcvNErtzb77gTuhgbVcLXUGIwZdhqty','Agent','Actif','default.png','Non',0,NULL,'2026-10-29 14:42:50','2026-10-29 14:42:28','2026-10-29 13:31:12',NULL),(20,'MANARIYO','GUY Ernell','Masculin','+25764346117','mizero100@gmail.com','gmanariyo@lec.univ.local','MANAGUY','$2y$10$FB3f2lxt.Mrchvgtk.Sdi.g0OtgIs/IpHQyoWUuQb1X7QUiIg0u0y','Recteur','Actif','default.png','Non',0,NULL,'2026-10-29 16:21:19','2026-10-30 14:42:23','2026-10-29 15:11:23',NULL),(21,'MIZERO','Alicia','Feminin','+25764346117','mizero100@gmail.com','amizero@cmp.univ.local','mizalicia','$2y$10$JMQrgt0e4V7v8UUaqcCFveIMlHjcvGJcylIgc4bm6HDEaeVZL9qOi','Comptable','Actif','default.png','Non',0,NULL,'2026-10-30 14:40:16','2026-10-30 14:39:56','2026-10-29 15:14:06',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_generate_email_auth BEFORE INSERT ON users
FOR EACH ROW
BEGIN
    DECLARE v_base VARCHAR(50);
    DECLARE v_domain VARCHAR(20);
    DECLARE v_email VARCHAR(150);
    DECLARE v_counter INT DEFAULT 0;
    DECLARE v_safety INT DEFAULT 0;
    
    SET v_base = LEFT(
        LOWER(CONCAT(
            IFNULL(LEFT(NEW.prenom, 1), ''),
            REPLACE(IFNULL(NEW.nom, ''), ' ', '')
        )),
        15
    );
    
    IF v_base = '' OR v_base IS NULL THEN
        SET v_base = 'user';
    END IF;
    
    CASE NEW.role
        WHEN 'Etudiant'       THEN SET v_domain = 'etu';
        WHEN 'Professeur'     THEN SET v_domain = 'prof';
        WHEN 'Administrateur' THEN SET v_domain = 'adm';
        WHEN 'Comptable'      THEN SET v_domain = 'cmp';
        WHEN 'Doyen'          THEN SET v_domain = 'doy';
        WHEN 'Recteur'        THEN SET v_domain = 'rec';
        WHEN 'Lecteur'        THEN SET v_domain = 'lec';
        WHEN 'Agent'          THEN SET v_domain = 'ins';
        ELSE                       SET v_domain = 'usr';
    END CASE;
    
    SET v_email = CONCAT(v_base, '@', v_domain, '.univ.local');
    
    WHILE EXISTS (SELECT 1 FROM users WHERE email_auth = v_email) AND v_safety < 100 DO
        SET v_counter = v_counter + 1;
        SET v_safety = v_safety + 1;
        SET v_email = CONCAT(v_base, v_counter, '@', v_domain, '.univ.local');
    END WHILE;
    
    SET NEW.email_auth = v_email;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `users_backup_emails`
--

DROP TABLE IF EXISTS `users_backup_emails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users_backup_emails` (
  `id_user` int(11) NOT NULL DEFAULT 0,
  `nom` varchar(100) DEFAULT NULL,
  `prenom` varchar(100) DEFAULT NULL,
  `role` enum('Administrateur','Professeur','Comptable','Etudiant','Lecteur','Doyen','Recteur','Agent') NOT NULL,
  `email_auth` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users_backup_emails`
--

LOCK TABLES `users_backup_emails` WRITE;
/*!40000 ALTER TABLE `users_backup_emails` DISABLE KEYS */;
INSERT INTO `users_backup_emails` VALUES (3,'mizero','francis','Etudiant','fmizero.f.mizero@univ.local'),(5,'mizero','kersaint','Etudiant','kmizero.k.mizero@univ.local'),(6,'kaze','diella','Etudiant','dkaze.d.kaze@univ.local'),(7,'bn','ef','Lecteur','ebn.t.user@univ.local'),(8,'Mizero','Karel','Administrateur','kmizero.mizerok@univ.local'),(9,'Niyomwungere','Olyne','Doyen','oniyomwungere.niyomwungere@univ.local'),(12,'BIGIRIMANA','Marc','Professeur','mbigirimana.bigmarc@univ.local'),(13,'KARORERO','Happy Noé','Etudiant','hkarorero.h.karorero@univ.local');
/*!40000 ALTER TABLE `users_backup_emails` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `vue_cours_profs_dept`
--

DROP TABLE IF EXISTS `vue_cours_profs_dept`;
/*!50001 DROP VIEW IF EXISTS `vue_cours_profs_dept`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vue_cours_profs_dept` AS SELECT
 1 AS `nom_cours`,
  1 AS `nom`,
  1 AS `prenom`,
  1 AS `nom_dept` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vue_etudiants_classes`
--

DROP TABLE IF EXISTS `vue_etudiants_classes`;
/*!50001 DROP VIEW IF EXISTS `vue_etudiants_classes`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vue_etudiants_classes` AS SELECT
 1 AS `nom`,
  1 AS `prenom`,
  1 AS `niveau` */;
SET character_set_client = @saved_cs_client;

--
-- Dumping routines for database 'universite'
--
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `afficher_cours_par_classe` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `afficher_cours_par_classe`(IN `p_nom_dept` VARCHAR(100), IN `p_niveau` VARCHAR(50))
BEGIN

    DECLARE v_nb_dept INT;
    DECLARE v_nb_classe INT;

    -- Vérifier le département
    SELECT COUNT(*)
    INTO v_nb_dept
    FROM departements
    WHERE nom_dept = p_nom_dept;

    IF v_nb_dept = 0 THEN

        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Departement introuvable';

    ELSE

        -- Vérifier la classe dans ce département
        SELECT COUNT(*)
        INTO v_nb_classe
        FROM classes c
        JOIN departements d
            ON c.id_dept = d.id_dept
        WHERE d.nom_dept = p_nom_dept
        AND c.niveau = p_niveau;

        IF v_nb_classe = 0 THEN

            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Niveau introuvable pour ce departement';

        ELSE

            -- Afficher les cours
            SELECT
                d.nom_dept,
                c.niveau,
                co.nom_cours
            FROM departements d
            JOIN classes c
                ON d.id_dept = c.id_dept
            JOIN cours co
                ON c.id_classe = co.id_classe
            WHERE d.nom_dept = p_nom_dept
            AND c.niveau = p_niveau;

        END IF;

    END IF;

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `afficher_etudiants_par_classe` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `afficher_etudiants_par_classe`(IN `nom_departement` VARCHAR(100), IN `niveau_classe` VARCHAR(50))
BEGIN

    DECLARE v_nb_dept INT;
    DECLARE v_nb_classe INT;

    -- Vérifier le département
    SELECT COUNT(*)
    INTO v_nb_dept
    FROM departements
    WHERE nom_dept = nom_departement;

    IF v_nb_dept = 0 THEN

        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Departement introuvable';

    ELSE

        -- Vérifier la classe dans ce département
        SELECT COUNT(*)
        INTO v_nb_classe
        FROM classes c
        JOIN departements d
            ON c.id_dept = d.id_dept
        WHERE d.nom_dept = nom_departement
        AND c.niveau = niveau_classe;

        IF v_nb_classe = 0 THEN

            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Niveau introuvable pour ce departement';

        ELSE

            -- Afficher les cours
            SELECT
                d.nom_dept,
                c.niveau,
                e.nom,
                e.prenom
            FROM departements d
            JOIN classes c
                ON d.id_dept = c.id_dept
            JOIN etudiants e
                ON c.id_classe = e.id_classe
            WHERE d.nom_dept = nom_departement
            AND c.niveau = niveau_classe;

        END IF;

    END IF;

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `affiche_inscription_par_matricule` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `affiche_inscription_par_matricule`(IN `p_matricule` VARCHAR(50))
BEGIN

    DECLARE nb INT;

    -- vérifier si le matricule existe
    SELECT COUNT(*)
    INTO nb
    FROM etudiants
    WHERE matricule = p_matricule;

    IF nb = 0 THEN

        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Erreur : matricule introuvable';

    ELSE

        -- afficher les inscriptions
        SELECT 
            e.matricule,
            e.nom,
            e.prenom,
            i.id_inscription,
            i.date_inscription
        FROM etudiants e
        JOIN inscriptions i
            ON e.id_etudiant = i.id_etudiant
        WHERE e.matricule = p_matricule;

    END IF;

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `ajouter_etudiant` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `ajouter_etudiant`(IN `p_nom` VARCHAR(100), IN `p_prenom` VARCHAR(100), IN `p_date_naissance` DATE, IN `p_email` VARCHAR(100), IN `p_telephone` VARCHAR(20), IN `p_nom_dept` VARCHAR(100), IN `p_niveau` VARCHAR(50))
BEGIN

    DECLARE v_id_dept INT;
    DECLARE v_id_classe INT;

    -- Trouver département
    SELECT id_dept INTO v_id_dept
    FROM departements
    WHERE nom_dept = p_nom_dept;

    IF v_id_dept IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Departement introuvable';
    END IF;

    -- Trouver classe selon niveau + département
    SELECT id_classe INTO v_id_classe
    FROM classes
    WHERE niveau = p_niveau
    AND id_dept = v_id_dept;

    IF v_id_classe IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Classe introuvable';
    END IF;

    -- Insertion étudiant (matricule généré par trigger)
    INSERT INTO etudiants(
        nom,
        prenom,
        date_naissance,
        email,
        telephone,
        id_classe
    )
    VALUES(
        p_nom,
        p_prenom,
        p_date_naissance,
        p_email,
        p_telephone,
        v_id_classe
    );

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `ajouter_etudiant_par_niveau_dept` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `ajouter_etudiant_par_niveau_dept`(IN `p_nom` VARCHAR(100), IN `p_prenom` VARCHAR(100), IN `p_niveau` VARCHAR(50), IN `p_nom_dept` VARCHAR(100))
BEGIN

    DECLARE v_id_dept INT;
    DECLARE v_id_classe INT;

    
    SELECT id_dept INTO v_id_dept
    FROM departements
    WHERE nom_dept = p_nom_dept;

    
    SELECT id_classe INTO v_id_classe
    FROM classes
    WHERE niveau = p_niveau
    AND id_dept = v_id_dept;

    
    INSERT INTO etudiants(nom,prenom, id_classe)
    VALUES(p_nom,p_prenom, v_id_classe);

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `ajouter_inscription` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `ajouter_inscription`(IN `p_matricule` VARCHAR(50))
BEGIN

    DECLARE v_id_etudiant INT;

    -- Rechercher l'étudiant
    SELECT id_etudiant
    INTO v_id_etudiant
    FROM etudiants
    WHERE matricule = p_matricule
    LIMIT 1;

    -- Vérifier existence
    IF v_id_etudiant IS NULL THEN

        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Matricule introuvable';

    ELSE

        -- Insérer avec date automatique
        INSERT INTO inscriptions(
            id_etudiant,
            date_inscription
        )
        VALUES(
            v_id_etudiant,
            NOW()
        );

    END IF;

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `desactiver_user` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `desactiver_user`(IN user_name VARCHAR(50))
BEGIN
    SET @sql = CONCAT('ALTER USER ''', user_name, '''@''localhost'' ACCOUNT LOCK');
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `etudiant_info` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `etudiant_info`(IN `dept` VARCHAR(150))
BEGIN
	SELECT e.nom, e.prenom,c.niveau
	FROM etudiants e
	JOIN classes c ON e.id_classe = c.id_classe
	JOIN departements d ON c.id_dept = d.id_dept
	WHERE d.nom_dept = dept;
	END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `INSERT_DEPARTEMENT` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `INSERT_DEPARTEMENT`(`nom_depte` VARCHAR(50), `descriptiones` VARCHAR(200))
BEGIN
INSERT INTO departements(nom_dept,description) VALUES ( nom_depte,descriptiones);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `mise_a_jour_departement` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `mise_a_jour_departement`(IN `ancien_nom` VARCHAR(500), IN `nouveau_nom` VARCHAR(500), IN `p_description` TEXT)
BEGIN

    DECLARE nb INT;

    -- vérifier existence département
    SELECT COUNT(*) INTO nb
    FROM departements
    WHERE nom_dept = ancien_nom;

    -- si département inexistant
    IF nb = 0 THEN

        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Departement introuvable';

    ELSE

        -- mise à jour
        UPDATE departements

        SET nom_dept = nouveau_nom,
            description = p_description

        WHERE nom_dept = ancien_nom;

    END IF;

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `mise_a_jour_etudiant` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `mise_a_jour_etudiant`(IN `p_matricule_etudiant` VARCHAR(150), IN `p_nom` VARCHAR(100), IN `p_prenom` VARCHAR(100), IN `p_date_naissance` DATE, IN `p_email` VARCHAR(250), IN `p_tel` VARCHAR(50))
BEGIN
	DECLARE nb INT;
    SELECT COUNT(*) INTO nb
    FROM etudiants
    WHERE matricule = p_matricule_etudiant;
    
    IF nb = 0 THEN
    	SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Etudiant introuvable';
        
    ELSE
    	UPDATE etudiants
        SET nom = p_nom,
        	prenom = p_prenom,
            date_naissance = p_date_naissance,
            email = p_email,
            telephone = p_tel
        WHERE matricule = p_matricule_etudiant;
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `mise_a_jour_informatique` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `mise_a_jour_informatique`(IN `p_nom_dept` VARCHAR(500), IN `p_description` TEXT)
BEGIN

    UPDATE departements

    SET nom_dept = p_nom_dept,
        description = p_description

    WHERE nom_dept = 'Informatique';

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_ajouter_etudiant` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_ajouter_etudiant`(
    IN p_nom VARCHAR(100),
    IN p_prenom VARCHAR(100),
    IN p_sexe ENUM('Masculin','Feminin'),
    IN p_telephone VARCHAR(20),
    IN p_email VARCHAR(150),
    IN p_username VARCHAR(50),
    IN p_mot_de_passe VARCHAR(255),
    IN p_id_classe INT,
    IN p_titre ENUM('Nouveau','Redoublant'),
    IN p_date_naissance VARCHAR(20),
    IN p_annee_academique VARCHAR(9)
)
BEGIN
    DECLARE v_id_user INT;
    
    START TRANSACTION;
    
    -- Insertion dans users
    INSERT INTO users (nom, prenom, sexe, telephone, email, username, mot_de_passe, role, statut)
    VALUES (p_nom, p_prenom, p_sexe, p_telephone, p_email, p_username, p_mot_de_passe, 'Etudiant', 'Actif');
    
    SET v_id_user = LAST_INSERT_ID();
    
    -- Insertion dans etudiants
    INSERT INTO etudiants (id_user, id_classe, titre, date_naissance, annee_academique_entree)
    VALUES (v_id_user, p_id_classe, p_titre, p_date_naissance, p_annee_academique);
    
    COMMIT;
    
    -- Retourner l'id_user créé
    SELECT v_id_user AS id_user;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `supprimer_etudiant` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `supprimer_etudiant`(IN `p_matricule` VARCHAR(150))
BEGIN

    DECLARE nb INT;

    SELECT COUNT(*) INTO nb
    FROM etudiants
    WHERE matricule = p_matricule;

    IF nb = 0 THEN

        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Etudiant introuvable';

    ELSE

        DELETE FROM etudiants
        WHERE matricule = p_matricule;

    END IF;

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `supprimer_inscription` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `supprimer_inscription`(IN `p_id_inscription` INT)
BEGIN

    DECLARE nb INT;

    -- vérifier si l’inscription existe
    SELECT COUNT(*) INTO nb
    FROM inscriptions
    WHERE id_inscription = p_id_inscription;

    IF nb = 0 THEN

        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Inscription introuvable';

    ELSE

        DELETE FROM inscriptions
        WHERE id_inscription = p_id_inscription;

    END IF;

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `supprimer_inscription_par_etudiant` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `supprimer_inscription_par_etudiant`(IN `p_nom` VARCHAR(100), IN `p_prenom` VARCHAR(100))
BEGIN

    DECLARE v_id_etudiant INT;
    DECLARE nb INT;

    -- vérifier si étudiant existe
    SELECT COUNT(*) INTO nb
    FROM etudiants
    WHERE nom = p_nom
    AND prenom = p_prenom;

    IF nb = 0 THEN

        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Etudiant introuvable';

    ELSE

        -- récupérer id_etudiant
        SELECT id_etudiant INTO v_id_etudiant
        FROM etudiants
        WHERE nom = p_nom
        AND prenom = p_prenom
        LIMIT 1;

        -- supprimer inscription seulement
        DELETE FROM inscriptions
        WHERE id_etudiant = v_id_etudiant;

    END IF;

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `supprimer_inscription_par_matricule` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `supprimer_inscription_par_matricule`(IN `p_matricule` VARCHAR(50))
BEGIN

    DECLARE v_id_etudiant INT;
    DECLARE nb INT;

    -- vérifier si le matricule existe
    SELECT id_etudiant
    INTO v_id_etudiant
    FROM etudiants
    WHERE matricule = p_matricule
    LIMIT 1;

    IF v_id_etudiant IS NULL THEN

        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Erreur : matricule introuvable';

    ELSE

        -- vérifier si inscription existe
        SELECT COUNT(*)
        INTO nb
        FROM inscriptions
        WHERE id_etudiant = v_id_etudiant;

        IF nb = 0 THEN

            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Erreur : aucune inscription pour cet etudiant';

        ELSE

            -- suppression
            DELETE FROM inscriptions
            WHERE id_etudiant = v_id_etudiant;

        END IF;

    END IF;

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `update_inscription` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `update_inscription`(IN `p_matricule` VARCHAR(50), IN `p_nouvelle_date_inscription` DATE)
BEGIN

    DECLARE v_id_etudiant INT;

    -- Vérifier si le matricule existe
    SELECT id_etudiant
    INTO v_id_etudiant
    FROM etudiants
    WHERE matricule = p_matricule
    LIMIT 1;

    IF v_id_etudiant IS NULL THEN

        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Matricule introuvable';

    ELSE

        UPDATE inscriptions
        SET date_inscription = p_nouvelle_date_inscription
        WHERE id_etudiant = v_id_etudiant;

    END IF;

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Final view structure for view `vue_cours_profs_dept`
--

/*!50001 DROP VIEW IF EXISTS `vue_cours_profs_dept`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8 */;
/*!50001 SET character_set_results     = utf8 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vue_cours_profs_dept` AS select 1 AS `nom_cours`,1 AS `nom`,1 AS `prenom`,1 AS `nom_dept` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vue_etudiants_classes`
--

/*!50001 DROP VIEW IF EXISTS `vue_etudiants_classes`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8 */;
/*!50001 SET character_set_results     = utf8 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vue_etudiants_classes` AS select 1 AS `nom`,1 AS `prenom`,1 AS `niveau` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-30 15:49:35
