-- MySQL dump 10.13  Distrib 8.0.40, for Win64 (x86_64)
--
-- Host: ho-cad118    Database: mldb
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
-- Table structure for table `nature_of_business`
--

DROP TABLE IF EXISTS `nature_of_business`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nature_of_business` (
  `id` int NOT NULL AUTO_INCREMENT,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `nature_of_business`
--

LOCK TABLES `nature_of_business` WRITE;
/*!40000 ALTER TABLE `nature_of_business` DISABLE KEYS */;
INSERT INTO `nature_of_business` VALUES (1,'Transportation'),(2,'Financing'),(3,'Telecommunications'),(4,'Review Center'),(5,'NGO - Foundation'),(6,'Banking and Finance'),(7,'Cotabato Light & Power Company'),(8,'Commercial Bank'),(9,'Davao Light & Power Co., Inc.'),(10,'Financial Services'),(11,'Lending Company'),(12,'Transcycle'),(13,'Collection, Purification, Distribution of Water'),(14,'Water Utilities'),(15,'Wholesale/Retail General Merchandise'),(16,'Manila Water Company, Inc.'),(17,'Electric Distribution Utility'),(18,'Air Transport'),(19,'PLDT INC.'),(20,'Powercycle Inc.'),(21,'RAFI Micro-Finance Inc.'),(22,'Microfinance'),(23,'Subic Enerzone Corporation'),(24,'Unistar Credit & Finance Corporation'),(25,'Visayan Electric Company, Inc.'),(26,'Retail of Water Purifiers');
/*!40000 ALTER TABLE `nature_of_business` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-16 10:32:33
