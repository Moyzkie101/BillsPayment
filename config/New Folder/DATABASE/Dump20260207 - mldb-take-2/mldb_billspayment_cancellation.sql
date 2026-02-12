-- MySQL dump 10.13  Distrib 8.0.40, for Win64 (x86_64)
--
-- Host: localhost    Database: mldb
-- ------------------------------------------------------
-- Server version	8.0.37

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
-- Table structure for table `billspayment_cancellation`
--

DROP TABLE IF EXISTS `billspayment_cancellation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billspayment_cancellation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cancellation_datetime` datetime DEFAULT NULL,
  `sendout_datetime` datetime DEFAULT NULL,
  `source_file` varchar(10) DEFAULT NULL,
  `control_no` varchar(150) DEFAULT NULL,
  `reference_no` varchar(150) DEFAULT NULL,
  `ir_no` varchar(150) DEFAULT NULL,
  `payor` varchar(500) DEFAULT NULL,
  `account_no` varchar(150) DEFAULT NULL,
  `account_name` varchar(150) DEFAULT NULL,
  `principal_amount` decimal(18,2) DEFAULT NULL,
  `charge_to_customer` decimal(18,2) DEFAULT NULL,
  `charge_to_partner` decimal(18,2) DEFAULT NULL,
  `cancellation_charge` decimal(18,2) DEFAULT NULL,
  `resource` varchar(500) DEFAULT NULL,
  `branch_id` varchar(50) DEFAULT NULL,
  `branch_code` varchar(5) DEFAULT NULL,
  `branch_name` varchar(150) DEFAULT NULL,
  `zone_code` varchar(4) DEFAULT NULL,
  `region_code` varchar(45) DEFAULT NULL,
  `region` varchar(150) DEFAULT NULL,
  `remote_branch` varchar(100) DEFAULT NULL,
  `remote_operator` varchar(100) DEFAULT NULL,
  `partner_name` varchar(150) DEFAULT NULL,
  `partner_id` varchar(150) DEFAULT NULL,
  `partner_id_kpx` varchar(150) DEFAULT NULL,
  `mpm_gl_code` varchar(50) DEFAULT NULL,
  `imported_by` varchar(150) DEFAULT NULL,
  `imported_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`) /*!80000 INVISIBLE */,
  KEY `index_partner` (`partner_id`,`partner_id_kpx`,`mpm_gl_code`),
  KEY `index_all` (`reference_no`,`control_no`,`source_file`,`sendout_datetime`,`cancellation_datetime`,`ir_no`,`branch_id`,`branch_code`,`zone_code`,`region_code`,`imported_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `billspayment_cancellation`
--

LOCK TABLES `billspayment_cancellation` WRITE;
/*!40000 ALTER TABLE `billspayment_cancellation` DISABLE KEYS */;
/*!40000 ALTER TABLE `billspayment_cancellation` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-04 18:03:14
