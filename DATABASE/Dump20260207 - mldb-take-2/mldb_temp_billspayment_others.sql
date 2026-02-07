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
-- Table structure for table `temp_billspayment_others`
--

DROP TABLE IF EXISTS `temp_billspayment_others`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `temp_billspayment_others` (
  `id` int NOT NULL AUTO_INCREMENT,
  `status` varchar(15) NOT NULL,
  `blank` varchar(50) NOT NULL,
  `date_time` varchar(150) NOT NULL,
  `control_number` varchar(150) NOT NULL,
  `reference_number` varchar(150) NOT NULL,
  `payor` varchar(150) NOT NULL,
  `address` varchar(150) NOT NULL,
  `account_number` varchar(150) NOT NULL,
  `account_name` varchar(150) NOT NULL,
  `amount_paid` decimal(15,2) NOT NULL,
  `charge_to_partner` decimal(15,2) NOT NULL,
  `charge_to_customer` decimal(15,2) NOT NULL,
  `contact_number` varchar(25) NOT NULL,
  `other_details` varchar(200) NOT NULL,
  `ml_outlet` varchar(200) NOT NULL,
  `region` varchar(200) NOT NULL,
  `operator` varchar(250) NOT NULL,
  `partner_name` varchar(250) NOT NULL,
  `partner_id` varchar(150) NOT NULL,
  `imported_date` datetime NOT NULL,
  `imported_by` varchar(250) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-07 13:38:39
