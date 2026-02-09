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
-- Table structure for table `soa_transaction`
--

DROP TABLE IF EXISTS `soa_transaction`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `soa_transaction` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date` date DEFAULT NULL,
  `reference_number` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `partner_Name` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `billing_period` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `partner_Tin` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` varchar(250) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `business_style` varchar(250) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `service_charge` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `from_date` date DEFAULT NULL,
  `to_date` date DEFAULT NULL,
  `po_number` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `number_of_transactions` int DEFAULT NULL,
  `amount` decimal(18,2) DEFAULT NULL,
  `add_amount` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `amount_add` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `numberOf_days` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `formula` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `formula_withheld` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `formulaInc_Exc` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `vat_amount` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `net_of_vat` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `withholding_tax` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `totalAmountDue` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `net_amount_due` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `prepared_by` varchar(250) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reasonOf_cancellation` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cancelled_by` varchar(250) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cancelled_date` date DEFAULT NULL,
  `reviewed_by` varchar(250) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `noted_by` varchar(250) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `preparedDate_signature` varchar(250) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `prepared_signature` varchar(250) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reviewed_signature` varchar(250) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reviewedDate_signature` varchar(250) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `noted_signature` varchar(250) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notedDate_signature` varchar(250) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reviewedFix_signature` varchar(250) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notedFix_signature` varchar(250) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` varchar(250) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2904 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
