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
-- Table structure for table `main_zone_masterfile`
--

DROP TABLE IF EXISTS `main_zone_masterfile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `main_zone_masterfile` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_zone_code` varchar(15) DEFAULT NULL,
  `main_zone_description` varchar(200) DEFAULT NULL,
  `created_by` varchar(150) DEFAULT NULL,
  `system_date` date DEFAULT NULL,
  `modified_by` varchar(150) DEFAULT NULL,
  `modified_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `index_all` (`main_zone_code`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `main_zone_masterfile`
--

LOCK TABLES `main_zone_masterfile` WRITE;
/*!40000 ALTER TABLE `main_zone_masterfile` DISABLE KEYS */;
INSERT INTO `main_zone_masterfile` VALUES (1,'JEW','Jewelry','Christian Kyle P. Autida','2023-08-17',NULL,NULL),(2,'HO','Head Office','Christian Kyle P. Autida','2023-08-17',NULL,NULL),(3,'LNCR','Luzon and NCR Zone','Christian Kyle P. Autida','2023-08-17',NULL,NULL),(4,'VISMIN','Visayas and Mindanao Zone','Christian Kyle P. Autida','2023-08-17',NULL,NULL);
/*!40000 ALTER TABLE `main_zone_masterfile` ENABLE KEYS */;
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
