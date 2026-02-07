-- MySQL dump 10.13  Distrib 8.0.40, for Win64 (x86_64)
--
-- Host: ho-cad118    Database: masterdata
-- ------------------------------------------------------
-- Server version	8.0.34

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `zone_masterfile`
--

DROP TABLE IF EXISTS `zone_masterfile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `zone_masterfile` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `zone_code` varchar(45) DEFAULT NULL,
  `zone_description` varchar(45) DEFAULT NULL,
  `main_zone_code` varchar(45) DEFAULT NULL,
  `created_by` varchar(45) DEFAULT NULL,
  `system_date` date DEFAULT NULL,
  `modified_by` varchar(45) DEFAULT NULL,
  `modified_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `index_all` (`zone_code`,`main_zone_code`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zone_masterfile`
--

LOCK TABLES `zone_masterfile` WRITE;
/*!40000 ALTER TABLE `zone_masterfile` DISABLE KEYS */;
INSERT INTO `zone_masterfile` VALUES (1,'HO','Head Office','HO','Christian Kyle P. Autida','2023-08-17',NULL,NULL),(2,'JEW','Jewelry','JEW','Christian Kyle P. Autida','2023-08-17',NULL,NULL),(3,'LZN','Luzon Zone','LNCR','Christian Kyle P. Autida','2023-08-17',NULL,NULL),(4,'NCR','National Capital Region Zone','LNCR','Christian Kyle P. Autida','2023-08-17',NULL,NULL),(5,'VIS','Visayas Zone','VISMIN','Christian Kyle P. Autida','2023-08-17',NULL,NULL),(6,'MIN','MIndanao Zone','VISMIN','Christian Kyle P. Autida','2023-08-17',NULL,NULL),(8,'VISMIN-MANCOMM','VISMIN Mancom','VISMIN','Cristo Ray M. Corales','2025-05-28',NULL,NULL),(9,'LNCR-MANCOMM','LNCR Mancom','LNCR','Cristo Ray M. Corales','2025-05-28',NULL,NULL),(10,'VISMIN-SUPPORT','VISMIN Support','VISMIN','Cristo Ray M. Corales','2025-05-28',NULL,NULL),(11,'LNCR-SUPPORT','LNCR Support','LNCR','Cristo Ray M. Corales','2025-05-28',NULL,NULL);
/*!40000 ALTER TABLE `zone_masterfile` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-07 10:21:39
