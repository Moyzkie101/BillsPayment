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
-- Table structure for table `billspayment_transaction`
--

DROP TABLE IF EXISTS `billspayment_transaction`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billspayment_transaction` (
  `id` int NOT NULL AUTO_INCREMENT,
  `status` varchar(50) DEFAULT NULL,
  `billing_invoice` varchar(45) DEFAULT NULL,
  `datetime` datetime DEFAULT NULL,
  `cancellation_date` datetime DEFAULT NULL,
  `source_file` varchar(10) DEFAULT NULL,
  `control_no` varchar(150) DEFAULT NULL,
  `reference_no` varchar(150) DEFAULT NULL,
  `payor` varchar(500) DEFAULT NULL,
  `address` varchar(150) DEFAULT NULL,
  `account_no` varchar(150) DEFAULT NULL,
  `account_name` varchar(150) DEFAULT NULL,
  `amount_paid` decimal(13,2) DEFAULT NULL,
  `charge_to_customer` decimal(13,2) DEFAULT NULL,
  `charge_to_partner` decimal(13,2) DEFAULT NULL,
  `contact_no` varchar(45) DEFAULT NULL,
  `other_details` varchar(255) DEFAULT NULL,
  `branch_id` varchar(50) NOT NULL,
  `branch_code` varchar(5) DEFAULT NULL,
  `outlet` varchar(150) DEFAULT NULL,
  `zone_code` varchar(4) DEFAULT NULL,
  `region_code` varchar(45) DEFAULT NULL,
  `region` varchar(150) DEFAULT NULL,
  `operator` varchar(150) DEFAULT NULL,
  `remote_branch` varchar(100) DEFAULT NULL,
  `remote_operator` varchar(100) DEFAULT NULL,
  `2nd_approver` varchar(100) DEFAULT NULL,
  `partner_name` varchar(150) DEFAULT NULL,
  `partner_id` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `partner_id_kpx` varchar(150) DEFAULT NULL,
  `mpm_gl_code` varchar(50) DEFAULT NULL,
  `settle_unsettle` varchar(45) DEFAULT NULL,
  `claim_unclaim` varchar(45) DEFAULT NULL,
  `imported_by` varchar(150) DEFAULT NULL,
  `imported_date` varchar(45) DEFAULT NULL,
  `rfp_no` varchar(100) DEFAULT NULL,
  `cad_no` varchar(100) DEFAULT NULL,
  `hold_status` varchar(45) DEFAULT NULL,
  `post_transaction` varchar(9) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `index_status` (`status`,`cancellation_date`),
  KEY `index_datetime` (`datetime`),
  KEY `index_cancellation_date` (`cancellation_date`),
  KEY `index_reference_no` (`reference_no`,`cancellation_date`,`datetime`,`status`,`partner_id`,`partner_id_kpx`,`post_transaction`)
) ENGINE=InnoDB AUTO_INCREMENT=1097459 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-07 13:38:40
