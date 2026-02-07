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
-- Table structure for table `billspayment_feedback`
--

DROP TABLE IF EXISTS `billspayment_feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billspayment_feedback` (
  `id` int NOT NULL AUTO_INCREMENT,
  `certified_datetime` datetime DEFAULT NULL,
  `source_file` varchar(10) DEFAULT NULL,
  `account_no` varchar(20) DEFAULT NULL,
  `lastname` varchar(30) DEFAULT NULL,
  `firstname` varchar(30) DEFAULT NULL,
  `middlename` varchar(30) DEFAULT NULL,
  `type_of_loan` varchar(15) DEFAULT NULL,
  `principal_amount` varchar(13) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `timestamp` varchar(15) DEFAULT NULL,
  `reference_no` varchar(17) DEFAULT NULL,
  `additional_ref_code` varchar(19) DEFAULT NULL,
  `phone_no` varchar(20) DEFAULT NULL,
  `status1` varchar(1) DEFAULT NULL,
  `branch_name` varchar(21) DEFAULT NULL,
  `status2` varchar(225) DEFAULT NULL,
  `partner_name` varchar(150) DEFAULT NULL,
  `kp7partner_id` varchar(150) DEFAULT NULL,
  `kpxpartner_id` varchar(150) DEFAULT NULL,
  `mpm_gl_code` varchar(50) DEFAULT NULL,
  `mbp_branch_id` varchar(50) DEFAULT NULL,
  `mrm_region_code` varchar(45) DEFAULT NULL,
  `mrm_zone_code` varchar(45) DEFAULT NULL,
  `mbp_mlmatic_region_name` varchar(150) DEFAULT NULL,
  `uploaded_date` date DEFAULT NULL,
  `uploaded_by` varchar(100) DEFAULT NULL,
  `user_type` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1021 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
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
