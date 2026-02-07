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
-- Table structure for table `actionlog`
--

DROP TABLE IF EXISTS `actionlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `actionlog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `status` varchar(15) DEFAULT NULL,
  `blank` varchar(50) DEFAULT NULL,
  `date_time` datetime DEFAULT NULL,
  `control_number` varchar(150) DEFAULT NULL,
  `reference_number` varchar(150) DEFAULT NULL,
  `payor` varchar(150) DEFAULT NULL,
  `address` varchar(150) DEFAULT NULL,
  `account_number` varchar(150) DEFAULT NULL,
  `account_name` varchar(150) DEFAULT NULL,
  `amount_paid` decimal(15,2) DEFAULT NULL,
  `charge_to_partner` decimal(15,2) DEFAULT NULL,
  `charge_to_customer` decimal(15,2) DEFAULT NULL,
  `contact_number` varchar(25) DEFAULT NULL,
  `other_details` text,
  `ml_outlet` varchar(200) DEFAULT NULL,
  `region` varchar(200) DEFAULT NULL,
  `operator` varchar(250) DEFAULT NULL,
  `partner_name` varchar(250) DEFAULT NULL,
  `partner_id` varchar(150) DEFAULT NULL,
  `imported_date` datetime DEFAULT NULL,
  `imported_by` varchar(250) DEFAULT NULL,
  `logged_by` varchar(250) DEFAULT NULL,
  `remark1` text,
  `date1` varchar(150) DEFAULT NULL,
  `remark_by1` varchar(250) DEFAULT NULL,
  `remark2` text,
  `date2` varchar(150) DEFAULT NULL,
  `remark_by2` varchar(250) DEFAULT NULL,
  `remark3` text,
  `date3` varchar(150) DEFAULT NULL,
  `remark_by3` varchar(250) DEFAULT NULL,
  `remark4` text,
  `date4` varchar(150) DEFAULT NULL,
  `remark_by4` varchar(250) DEFAULT NULL,
  `remark5` text,
  `date5` varchar(150) DEFAULT NULL,
  `remark_by5` varchar(250) DEFAULT NULL,
  `remark6` text,
  `date6` varchar(150) DEFAULT NULL,
  `remark_by6` varchar(250) DEFAULT NULL,
  `remark7` text,
  `date7` varchar(150) DEFAULT NULL,
  `remark_by7` varchar(250) DEFAULT NULL,
  `remark8` text,
  `date8` varchar(150) DEFAULT NULL,
  `remark_by8` varchar(250) DEFAULT NULL,
  `remark9` text,
  `date9` varchar(150) DEFAULT NULL,
  `remark_by9` varchar(250) DEFAULT NULL,
  `remark10` text,
  `date10` varchar(150) DEFAULT NULL,
  `remark_by10` varchar(250) DEFAULT NULL,
  `log_status` varchar(100) DEFAULT NULL,
  `logged_date` varchar(100) DEFAULT NULL,
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

-- Dump completed on 2026-02-07 13:38:40
