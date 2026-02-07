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
-- Table structure for table `corporate_name_masterfile`
--

DROP TABLE IF EXISTS `corporate_name_masterfile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `corporate_name_masterfile` (
  `id` int NOT NULL AUTO_INCREMENT,
  `corporate_name` varchar(100) DEFAULT NULL,
  `created_by` varchar(45) DEFAULT NULL,
  `system_date` date DEFAULT NULL,
  `modified_by` varchar(45) DEFAULT NULL,
  `modified_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idcoporate_name_masterfile_UNIQUE` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `corporate_name_masterfile`
--

LOCK TABLES `corporate_name_masterfile` WRITE;
/*!40000 ALTER TABLE `corporate_name_masterfile` DISABLE KEYS */;
INSERT INTO `corporate_name_masterfile` VALUES (4,'AMPARITO LLAMAS LHUILLIER FINANCIAL SERVICES (PAWNSHOPS), INC.','Cristo Ray M. Corales','2025-04-25',NULL,NULL),(5,'MICHEL J. LHUILLIER FINANCIAL SERVICES (PAWNSHOPS), INC.','Cristo Ray M. Corales','2025-04-25',NULL,NULL),(6,'LHUILLIER JEWEL FINANCIAL SERVICES (PAWNSHOPS), INC.','Cristo Ray M. Corales','2025-04-25',NULL,NULL),(7,'TRUSTWORTHY (PAWNSHOPS), INC.','Cristo Ray M. Corales','2025-04-25',NULL,NULL),(8,'CEBU MABUHAY (PAWNSHOPS), INC.','Cristo Ray M. Corales','2025-04-25',NULL,NULL),(9,'KWIK LOANS PAWNSHOPS INC.','Cristo Ray M. Corales','2025-04-25',NULL,NULL);
/*!40000 ALTER TABLE `corporate_name_masterfile` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-07 10:21:38
