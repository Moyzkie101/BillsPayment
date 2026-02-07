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
-- Table structure for table `business_styles`
--

DROP TABLE IF EXISTS `business_styles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `business_styles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `business_style` varchar(100) NOT NULL,
  `created_by` varchar(45) DEFAULT NULL,
  `system_date` date DEFAULT NULL,
  `modified_by` varchar(45) DEFAULT NULL,
  `modified_date` date DEFAULT NULL,
  UNIQUE KEY `id_UNIQUE` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `business_styles`
--

LOCK TABLES `business_styles` WRITE;
/*!40000 ALTER TABLE `business_styles` DISABLE KEYS */;
INSERT INTO `business_styles` VALUES (2,'Telecommunications','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(3,'Review Center','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(4,'NGO - Foundation','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(5,'Banking and Finance','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(6,'Cotabato Light & Power Company','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(7,'Commercial Bank','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(8,'Davao Light & Power Co., Inc.','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(9,'Financial Services','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(10,'Lending Company','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(11,'Transcycle','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(12,'Collection, Purification, Distribution of Water','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(13,'NONE','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(14,'Water Utilities','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(15,'Wholesale/Retail General Merchandise','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(16,'Manila Water Company, Inc.','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(17,'Electric Distribution Utility','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(18,'Air Transport','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(19,'PLDT INC.','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(20,'Powercycle Inc.','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(21,'RAFI Micro-Finance Inc.','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(22,'TELECOM','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(23,'Financing Company','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(24,'Microfinance','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(25,'Subic Enerzone Corporation','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(26,'Unistar Credit & Finance Corporation','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(27,'Visayan Electric Company, Inc.','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(28,'Retail of Water Purifiers','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(29,'Transportation','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(32,'Financing','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(33,'Telecommunications','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(34,'Review Center','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(35,'NGO - Foundation','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(36,'Banking and Finance','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(37,'Cotabato Light & Power Company','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(38,'Commercial Bank','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(39,'Davao Light & Power Co., Inc.','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(40,'Financial Services','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(41,'Lending Company','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(42,'Transcycle','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(43,'Collection, Purification, Distribution of Water','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(44,'NONE','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(45,'Water Utilities','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(46,'Wholesale/Retail General Merchandise','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(47,'Manila Water Company, Inc.','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(48,'Electric Distribution Utility','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(49,'Air Transport','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(50,'PLDT INC.','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(51,'Powercycle Inc.','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(52,'RAFI Micro-Finance Inc.','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(53,'TELECOM','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(54,'Financing Company','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(55,'Microfinance','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(56,'Subic Enerzone Corporation','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(57,'Unistar Credit & Finance Corporation','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(58,'Visayan Electric Company, Inc.','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(59,'Retail of Water Purifiers','Cristo Ray M. Corales','2025-06-04',NULL,NULL),(60,'Transportation','Cristo Ray M. Corales','2025-06-04',NULL,NULL);
/*!40000 ALTER TABLE `business_styles` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-07 10:21:40
