-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: lumina_db
-- ------------------------------------------------------
-- Server version	8.0.46

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `cart_items`
--

DROP TABLE IF EXISTS `cart_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cart_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cart_id` bigint unsigned NOT NULL,
  `product_variant_id` bigint unsigned NOT NULL,
  `lens_option_id` bigint unsigned DEFAULT NULL,
  `prescription_id` bigint unsigned DEFAULT NULL,
  `order_type` enum('available','preorder','prescription') NOT NULL DEFAULT 'available',
  `quantity` int NOT NULL DEFAULT '1',
  `unit_price` decimal(12,2) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_cart_items_variant` (`product_variant_id`),
  KEY `fk_cart_items_lens` (`lens_option_id`),
  KEY `fk_cart_items_prescription` (`prescription_id`),
  KEY `idx_cart_items_cart_id` (`cart_id`),
  KEY `idx_cart_items_order_type` (`order_type`),
  CONSTRAINT `fk_cart_items_cart` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cart_items_lens` FOREIGN KEY (`lens_option_id`) REFERENCES `lens_options` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_cart_items_prescription` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_cart_items_variant` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cart_items`
--

LOCK TABLES `cart_items` WRITE;
/*!40000 ALTER TABLE `cart_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `cart_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `carts`
--

DROP TABLE IF EXISTS `carts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `carts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `fk_carts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `carts`
--

LOCK TABLES `carts` WRITE;
/*!40000 ALTER TABLE `carts` DISABLE KEYS */;
/*!40000 ALTER TABLE `carts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `category_type` enum('frame','sunglasses','lens','service','other') NOT NULL DEFAULT 'other',
  `description` text,
  `image_url` varchar(255) DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `uq_categories_name_parent` (`name`,`parent_id`),
  KEY `idx_categories_parent_id` (`parent_id`),
  KEY `idx_categories_type_active` (`category_type`,`is_active`),
  CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,NULL,'Gọng kính','gong-kinh','frame',NULL,NULL,1,1,'2026-06-01 08:37:26','2026-06-01 08:37:26'),(2,NULL,'Kính mát','kinh-mat','sunglasses',NULL,NULL,2,1,'2026-06-01 08:37:26','2026-06-01 08:37:26'),(3,NULL,'Tròng kính','trong-kinh','lens',NULL,NULL,3,1,'2026-06-01 08:37:26','2026-06-01 08:37:26'),(4,1,'Gọng kính kim loại','gong-kinh-kim-loai','frame',NULL,NULL,1,1,'2026-06-01 08:37:26','2026-06-01 08:37:26'),(5,1,'Gọng kính oval','gong-kinh-oval','frame',NULL,NULL,2,1,'2026-06-01 08:37:26','2026-06-01 08:37:26'),(6,1,'Gọng kính mắt mèo','gong-kinh-mat-meo','frame',NULL,NULL,3,1,'2026-06-01 08:37:26','2026-06-01 08:37:26'),(7,1,'Gọng kính nhựa','gong-kinh-nhua','frame',NULL,NULL,4,1,'2026-06-01 08:37:26','2026-06-01 08:37:26'),(8,2,'Kính mát nam','kinh-mat-nam','sunglasses',NULL,NULL,1,1,'2026-06-01 08:37:26','2026-06-01 08:37:26'),(9,2,'Kính mát nữ','kinh-mat-nu','sunglasses',NULL,NULL,2,1,'2026-06-01 08:37:26','2026-06-01 08:37:26'),(10,2,'Kính mát em bé','kinh-mat-em-be','sunglasses',NULL,NULL,3,1,'2026-06-01 08:37:26','2026-06-01 08:37:26'),(11,3,'Tròng chống ánh sáng xanh','trong-chong-anh-sang-xanh','lens',NULL,NULL,1,1,'2026-06-01 08:37:26','2026-06-01 08:37:26'),(12,3,'Tròng đổi màu','trong-doi-mau','lens',NULL,NULL,2,1,'2026-06-01 08:37:26','2026-06-01 08:37:26');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_prescriptions`
--

DROP TABLE IF EXISTS `customer_prescriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_prescriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `profile_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '????n k??nh c???a t??i',
  `od_sphere` decimal(5,2) DEFAULT NULL COMMENT 'C???u (SPH) m???t ph???i, t??? -20 ?????n +20',
  `od_cylinder` decimal(5,2) DEFAULT NULL COMMENT 'Tr??? (CYL) m???t ph???i',
  `od_axis` smallint unsigned DEFAULT NULL COMMENT 'Tr???c (AXIS) m???t ph???i 0-180',
  `od_addition` decimal(5,2) DEFAULT NULL COMMENT 'C???ng th??m (ADD) m???t ph???i, cho k??nh l??o',
  `os_sphere` decimal(5,2) DEFAULT NULL COMMENT 'C???u (SPH) m???t tr??i',
  `os_cylinder` decimal(5,2) DEFAULT NULL COMMENT 'Tr??? (CYL) m???t tr??i',
  `os_axis` smallint unsigned DEFAULT NULL COMMENT 'Tr???c (AXIS) m???t tr??i 0-180',
  `os_addition` decimal(5,2) DEFAULT NULL COMMENT 'C???ng th??m (ADD) m???t tr??i',
  `pd_right` decimal(5,2) DEFAULT NULL COMMENT 'PD m???t ph???i (mm)',
  `pd_left` decimal(5,2) DEFAULT NULL COMMENT 'PD m???t tr??i (mm)',
  `pd_distance` decimal(5,2) DEFAULT NULL COMMENT 'PD t???ng khi nh??n xa (mm)',
  `pd_near` decimal(5,2) DEFAULT NULL COMMENT 'PD t???ng khi nh??n g???n (mm)',
  `note` text COLLATE utf8mb4_unicode_ci,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cust_presc_user` (`user_id`),
  KEY `idx_cust_presc_default` (`user_id`,`is_default`),
  CONSTRAINT `fk_cust_presc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_prescriptions`
--

LOCK TABLES `customer_prescriptions` WRITE;
/*!40000 ALTER TABLE `customer_prescriptions` DISABLE KEYS */;
INSERT INTO `customer_prescriptions` VALUES (4,23,'Đơn kính cận đi làm',-2.50,-0.75,175,NULL,-2.25,-0.50,180,NULL,31.50,31.00,62.50,NULL,'Kính dùng để làm việc máy tính nhiều, tròng chống ánh sáng xanh.',1,'2026-06-22 12:26:20','2026-06-22 12:26:20'),(5,23,'Đơn kính đọc sách',1.50,NULL,NULL,1.25,1.25,NULL,NULL,1.25,30.00,30.00,60.00,NULL,'Đơn kính đọc sách, nhìn gần.',0,'2026-06-22 12:26:20','2026-06-22 12:26:20'),(6,27,'Kính cận học tập',-4.00,-1.00,160,NULL,-3.75,-1.25,165,NULL,32.00,32.00,64.00,NULL,'Mắt cận lệch và loạn thị nhẹ.',1,'2026-06-22 12:26:20','2026-06-22 12:26:20'),(7,18,'hồ sơ 1',0.75,10.00,33,3.00,3.00,10.00,123,1.00,31.00,31.00,65.00,65.00,'năm 2024',0,'2026-06-22 13:06:33','2026-06-22 13:06:33');
/*!40000 ALTER TABLE `customer_prescriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lens_options`
--

DROP TABLE IF EXISTS `lens_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lens_options` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `description` text,
  `lens_type` enum('single_vision','progressive','bifocal','non_prescription','other') NOT NULL DEFAULT 'single_vision',
  `coating` varchar(150) DEFAULT NULL,
  `refractive_index` varchar(50) DEFAULT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_lens_options_category` (`category_id`),
  KEY `idx_lens_options_active` (`is_active`),
  CONSTRAINT `fk_lens_options_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lens_options`
--

LOCK TABLES `lens_options` WRITE;
/*!40000 ALTER TABLE `lens_options` DISABLE KEYS */;
INSERT INTO `lens_options` VALUES (1,11,'Blue Cut 1.56','blue-cut-156','Tròng chống ánh sáng xanh cơ bản','single_vision','Blue Cut','1.56',350000.00,1,'2026-06-01 08:37:26','2026-06-01 08:37:26'),(2,12,'Photochromic 1.60','photochromic-160','Tròng đổi màu khi ra nắng','single_vision','UV + đổi màu','1.60',650000.00,1,'2026-06-01 08:37:26','2026-06-01 08:37:26');
/*!40000 ALTER TABLE `lens_options` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `product_variant_id` bigint unsigned NOT NULL,
  `lens_option_id` bigint unsigned DEFAULT NULL,
  `product_name` varchar(180) NOT NULL,
  `variant_sku` varchar(100) NOT NULL,
  `variant_snapshot` json DEFAULT NULL,
  `lens_snapshot` json DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `unit_price` decimal(12,2) NOT NULL,
  `lens_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_order_items_lens` (`lens_option_id`),
  KEY `idx_order_items_order_id` (`order_id`),
  KEY `idx_order_items_variant_id` (`product_variant_id`),
  CONSTRAINT `fk_order_items_lens` FOREIGN KEY (`lens_option_id`) REFERENCES `lens_options` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_variant` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (5,5,1,1,'LUMINA Air Oval','LMAIR-OVAL-BLK-M',NULL,NULL,1,890000.00,350000.00,1240000.00,'2026-06-22 12:26:20','2026-06-22 12:26:20'),(6,6,4,NULL,'LUMINA Sun Voyager','LMSUN-VOYAGER-BLK-L',NULL,NULL,2,990000.00,0.00,1980000.00,'2026-06-22 12:26:20','2026-06-22 12:26:20'),(7,7,3,2,'LUMINA Cat Eye Grace','LMCAT-GRACE-BRW-M',NULL,NULL,1,1190000.00,650000.00,1840000.00,'2026-06-22 12:26:20','2026-06-22 12:26:20'),(8,8,4,NULL,'LUMINA Sun Voyager','LMSUN-VOYAGER-BLK-L',NULL,NULL,1,990000.00,0.00,990000.00,'2026-06-22 12:26:20','2026-06-22 12:26:20'),(9,9,1,NULL,'LUMINA Air Oval','LMAIR-OVAL-BLK-M',NULL,NULL,1,890000.00,0.00,890000.00,'2026-06-22 12:26:20','2026-06-22 12:26:20'),(10,10,12,NULL,'Tròng Chemi U2 1.67','LENS-CHE-U2-167','{\"sku\": \"LENS-CHE-U2-167\", \"brand\": \"Chemi\", \"color\": \"Trong suốt\", \"shape\": \"Circular\", \"material\": null, \"thumbnail\": \"/assets/images/products/dd795d9bfac44af70e01b8011a2d0843.webp\", \"frame_type\": \"None\", \"product_id\": 13, \"size_label\": \"Standard\", \"variant_id\": 12, \"product_slug\": \"trong-chemi-u2-167\", \"category_name\": \"Tròng đổi màu\"}',NULL,2,680000.00,0.00,1360000.00,'2026-06-22 13:08:27','2026-06-22 13:08:27'),(11,10,6,NULL,'LUMINA Cat Eye Grace V2','LMCAT-GRACE-BLK-M','{\"sku\": \"LMCAT-GRACE-BLK-M\", \"brand\": \"LUMINA\", \"color\": \"Đen vàng\", \"shape\": \"Cat Eye\", \"material\": \"Nhựa Acetate\", \"thumbnail\": \"/assets/images/products/b63f8092ebee6bbe8e49c27b61ef6505.webp\", \"frame_type\": \"Cat Eye\", \"product_id\": 7, \"size_label\": \"M\", \"variant_id\": 6, \"product_slug\": \"lumina-cat-eye-grace-v2\", \"category_name\": \"Gọng kính mắt mèo\"}',NULL,1,950000.00,0.00,950000.00,'2026-06-22 13:08:27','2026-06-22 13:08:27'),(12,10,1,NULL,'LUMINA Air Oval','LMAIR-OVAL-BLK-M','{\"sku\": \"LMAIR-OVAL-BLK-M\", \"brand\": \"LUMINA\", \"color\": \"Đen\", \"shape\": \"Oval\", \"material\": \"Kim loại\", \"thumbnail\": \"/assets/images/products/air-oval.jpg\", \"frame_type\": \"Oval\", \"product_id\": 1, \"size_label\": \"M\", \"variant_id\": 1, \"product_slug\": \"lumina-air-oval\", \"category_name\": \"Gọng kính oval\"}',NULL,1,890000.00,0.00,890000.00,'2026-06-22 13:08:27','2026-06-22 13:08:27'),(13,11,12,NULL,'Tròng Chemi U2 1.67','LENS-CHE-U2-167','{\"sku\": \"LENS-CHE-U2-167\", \"brand\": \"Chemi\", \"color\": \"Trong suốt\", \"shape\": \"Circular\", \"material\": null, \"thumbnail\": \"/assets/images/products/dd795d9bfac44af70e01b8011a2d0843.webp\", \"frame_type\": \"None\", \"product_id\": 13, \"size_label\": \"Standard\", \"variant_id\": 12, \"product_slug\": \"trong-chemi-u2-167\", \"category_name\": \"Tròng đổi màu\"}',NULL,2,680000.00,0.00,1360000.00,'2026-06-22 13:09:08','2026-06-22 13:09:08'),(14,11,6,NULL,'LUMINA Cat Eye Grace V2','LMCAT-GRACE-BLK-M','{\"sku\": \"LMCAT-GRACE-BLK-M\", \"brand\": \"LUMINA\", \"color\": \"Đen vàng\", \"shape\": \"Cat Eye\", \"material\": \"Nhựa Acetate\", \"thumbnail\": \"/assets/images/products/b63f8092ebee6bbe8e49c27b61ef6505.webp\", \"frame_type\": \"Cat Eye\", \"product_id\": 7, \"size_label\": \"M\", \"variant_id\": 6, \"product_slug\": \"lumina-cat-eye-grace-v2\", \"category_name\": \"Gọng kính mắt mèo\"}',NULL,1,950000.00,0.00,950000.00,'2026-06-22 13:09:08','2026-06-22 13:09:08'),(15,11,1,NULL,'LUMINA Air Oval','LMAIR-OVAL-BLK-M','{\"sku\": \"LMAIR-OVAL-BLK-M\", \"brand\": \"LUMINA\", \"color\": \"Đen\", \"shape\": \"Oval\", \"material\": \"Kim loại\", \"thumbnail\": \"/assets/images/products/air-oval.jpg\", \"frame_type\": \"Oval\", \"product_id\": 1, \"size_label\": \"M\", \"variant_id\": 1, \"product_slug\": \"lumina-air-oval\", \"category_name\": \"Gọng kính oval\"}',NULL,1,890000.00,0.00,890000.00,'2026-06-22 13:09:08','2026-06-22 13:09:08'),(16,12,6,NULL,'LUMINA Cat Eye Grace V2','LMCAT-GRACE-BLK-M','{\"sku\": \"LMCAT-GRACE-BLK-M\", \"brand\": \"LUMINA\", \"color\": \"Đen vàng\", \"shape\": \"Cat Eye\", \"material\": \"Nhựa Acetate\", \"thumbnail\": \"/assets/images/products/b63f8092ebee6bbe8e49c27b61ef6505.webp\", \"frame_type\": \"Cat Eye\", \"product_id\": 7, \"size_label\": \"M\", \"variant_id\": 6, \"product_slug\": \"lumina-cat-eye-grace-v2\", \"category_name\": \"Gọng kính mắt mèo\"}',NULL,2,950000.00,0.00,1900000.00,'2026-06-25 03:20:08','2026-06-25 03:20:08'),(17,13,6,NULL,'LUMINA Cat Eye Grace V2','LMCAT-GRACE-BLK-M','{\"sku\": \"LMCAT-GRACE-BLK-M\", \"brand\": \"LUMINA\", \"color\": \"Đen vàng\", \"shape\": \"Cat Eye\", \"material\": \"Nhựa Acetate\", \"thumbnail\": \"/assets/images/products/b63f8092ebee6bbe8e49c27b61ef6505.webp\", \"frame_type\": \"Cat Eye\", \"product_id\": 7, \"size_label\": \"M\", \"variant_id\": 6, \"product_slug\": \"lumina-cat-eye-grace-v2\", \"category_name\": \"Gọng kính mắt mèo\"}',NULL,2,950000.00,0.00,1900000.00,'2026-06-25 03:20:55','2026-06-25 03:20:55'),(18,14,12,NULL,'Tròng Chemi U2 1.67','LENS-CHE-U2-167','{\"sku\": \"LENS-CHE-U2-167\", \"brand\": \"Chemi\", \"color\": \"Trong suốt\", \"shape\": \"Circular\", \"material\": null, \"thumbnail\": \"/assets/images/products/dd795d9bfac44af70e01b8011a2d0843.webp\", \"frame_type\": \"None\", \"product_id\": 13, \"size_label\": \"Standard\", \"variant_id\": 12, \"product_slug\": \"trong-chemi-u2-167\", \"category_name\": \"Tròng đổi màu\"}',NULL,1,680000.00,0.00,680000.00,'2026-06-25 03:23:49','2026-06-25 03:23:49'),(19,15,12,NULL,'Tròng Chemi U2 1.67','LENS-CHE-U2-167','{\"sku\": \"LENS-CHE-U2-167\", \"brand\": \"Chemi\", \"color\": \"Trong suốt\", \"shape\": \"Circular\", \"material\": null, \"thumbnail\": \"/assets/images/products/dd795d9bfac44af70e01b8011a2d0843.webp\", \"frame_type\": \"None\", \"product_id\": 13, \"size_label\": \"Standard\", \"variant_id\": 12, \"product_slug\": \"trong-chemi-u2-167\", \"category_name\": \"Tròng đổi màu\"}',NULL,1,680000.00,0.00,680000.00,'2026-07-01 14:56:22','2026-07-01 14:56:22'),(20,16,10,NULL,'Tròng Essilor Crizal Rock 1.56','LENS-ESS-ROCK-156','{\"sku\": \"LENS-ESS-ROCK-156\", \"brand\": \"Essilor\", \"color\": \"Trong suốt\", \"shape\": \"Circular\", \"material\": null, \"thumbnail\": \"/assets/images/products/dfd66d2ec7b1ef0a79949619135342f5.webp\", \"frame_type\": \"None\", \"product_id\": 11, \"size_label\": \"Standard\", \"variant_id\": 10, \"product_slug\": \"trong-essilor-crizal-rock-156\", \"category_name\": \"Tròng chống ánh sáng xanh\"}',NULL,2,890000.00,0.00,1780000.00,'2026-07-01 14:57:25','2026-07-01 14:57:25');
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_status_logs`
--

DROP TABLE IF EXISTS `order_status_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_status_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `changed_by` bigint unsigned DEFAULT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_order_status_logs_changed_by` (`changed_by`),
  KEY `idx_order_status_logs_order_id` (`order_id`),
  KEY `idx_order_status_logs_created_at` (`created_at`),
  CONSTRAINT `fk_order_status_logs_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_order_status_logs_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_status_logs`
--

LOCK TABLES `order_status_logs` WRITE;
/*!40000 ALTER TABLE `order_status_logs` DISABLE KEYS */;
INSERT INTO `order_status_logs` VALUES (9,5,19,'pending','confirmed','Admin xác nhận thông tin đơn hàng.','2026-06-22 12:26:20'),(10,5,19,'confirmed','processing','Đang lắp tròng kính cận cho khách.','2026-06-22 12:26:20'),(11,5,19,'processing','shipping','Đơn hàng được giao cho đơn vị vận chuyển.','2026-06-22 12:26:20'),(12,5,19,'shipping','completed','Khách hàng đã nhận được hàng và thanh toán COD.','2026-06-22 12:26:20'),(13,6,NULL,'pending','confirmed','Hệ thống tự động xác nhận đơn hàng đã thanh toán qua VNPAY.','2026-06-22 12:26:20'),(14,6,19,'confirmed','shipping','Đóng gói sản phẩm và bàn giao vận chuyển.','2026-06-22 12:26:20'),(15,6,19,'shipping','completed','Giao hàng thành công.','2026-06-22 12:26:20'),(16,7,19,'pending','confirmed','Xác nhận chuyển khoản thành công.','2026-06-22 12:26:20'),(17,7,19,'confirmed','processing','Đang chuyển giao kỹ thuật gia công tròng đổi màu.','2026-06-22 12:26:20'),(18,7,19,'processing','shipping','Giao hàng qua bưu điện.','2026-06-22 12:26:20'),(19,10,NULL,NULL,'pending','Đơn hàng được tạo từ checkout công khai.','2026-06-22 13:08:27'),(20,11,NULL,NULL,'pending','Đơn hàng được tạo từ checkout công khai.','2026-06-22 13:09:08'),(21,11,19,'pending','completed','','2026-06-22 13:11:23'),(22,10,19,'pending','completed','','2026-06-22 13:11:30'),(23,12,NULL,NULL,'pending','Đơn hàng được tạo từ checkout công khai.','2026-06-25 03:20:08'),(24,13,NULL,NULL,'pending','Đơn hàng được tạo từ checkout công khai.','2026-06-25 03:20:55'),(25,14,NULL,NULL,'pending','Đơn hàng được tạo từ checkout công khai.','2026-06-25 03:23:49'),(26,14,NULL,'pending','pending','Thanh toán trực tuyến thành công qua VNPAY. Mã giao dịch: 15598069','2026-06-25 03:24:24'),(27,14,19,'pending','completed','','2026-06-25 03:24:58'),(28,15,NULL,NULL,'pending','Đơn hàng được tạo từ checkout công khai.','2026-07-01 14:56:22'),(29,15,18,'pending','cancelled','Khách hàng tự hủy đơn. Lý do: k ok','2026-07-01 14:56:32'),(30,16,NULL,NULL,'pending','Đơn hàng được tạo từ checkout công khai.','2026-07-01 14:57:25'),(31,16,19,'pending','completed','','2026-07-01 14:57:44'),(32,12,18,'pending','cancelled','Khách hàng tự hủy đơn. Lý do: okkkkkkk','2026-07-04 08:52:21');
/*!40000 ALTER TABLE `order_status_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `handled_by` bigint unsigned DEFAULT NULL,
  `order_code` varchar(30) NOT NULL,
  `order_type` enum('available','preorder','prescription','return_order') NOT NULL DEFAULT 'available',
  `status` enum('pending','awaiting_stock','checking_prescription','confirmed','processing','lens_processing','shipping','completed','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `customer_name` varchar(150) NOT NULL,
  `customer_email` varchar(150) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `shipping_address_line` varchar(255) NOT NULL,
  `shipping_ward` varchar(120) DEFAULT NULL,
  `shipping_district` varchar(120) DEFAULT NULL,
  `shipping_province` varchar(120) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `note` text,
  `internal_note` text,
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `lens_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `shipping_fee` decimal(12,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `payment_method` enum('cod','bank_transfer','momo','vnpay','other') NOT NULL DEFAULT 'cod',
  `payment_status` enum('unpaid','paid','partially_paid','failed','refunded') NOT NULL DEFAULT 'unpaid',
  `cancel_requested` tinyint(1) NOT NULL DEFAULT '0',
  `cancel_reason` varchar(255) DEFAULT NULL,
  `prescription_id` bigint unsigned DEFAULT NULL,
  `prescription_wallet_id` bigint unsigned DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `shipped_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_code` (`order_code`),
  KEY `fk_orders_handled_by` (`handled_by`),
  KEY `fk_orders_prescription` (`prescription_id`),
  KEY `idx_orders_user_id` (`user_id`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_type` (`order_type`),
  KEY `idx_orders_created_at` (`created_at`),
  KEY `idx_orders_payment_status` (`payment_status`),
  KEY `fk_orders_wallet_prescription` (`prescription_wallet_id`),
  CONSTRAINT `fk_orders_handled_by` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_prescription` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_wallet_prescription` FOREIGN KEY (`prescription_wallet_id`) REFERENCES `customer_prescriptions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (5,23,19,'LMN-ORD-001','prescription','completed','Phạm Thị Khách Hàng','customer1@lumina.vn','0912345678','123 Nguyễn Văn Cừ','Phường 4','Quận 5','TP. Hồ Chí Minh',NULL,NULL,NULL,890000.00,350000.00,30000.00,50000.00,1220000.00,'cod','paid',0,NULL,NULL,4,'2026-06-17 12:26:20','2026-06-18 12:26:20','2026-06-19 12:26:20',NULL,'2026-06-16 12:26:20','2026-06-22 12:26:20'),(6,27,19,'LMN-ORD-002','available','completed','Nguyễn Văn Mua Sắm','customer2@lumina.vn','0987654321','456 Lê Lợi','Phường Bến Thành','Quận 1','TP. Hồ Chí Minh',NULL,NULL,NULL,1980000.00,0.00,0.00,100000.00,1880000.00,'vnpay','paid',0,NULL,NULL,NULL,'2026-06-19 12:26:20','2026-06-20 12:26:20','2026-06-21 12:26:20',NULL,'2026-06-18 12:26:20','2026-06-22 12:26:20'),(7,23,19,'LMN-ORD-003','prescription','shipping','Phạm Thị Khách Hàng','customer1@lumina.vn','0912345678','123 Nguyễn Văn Cừ','Phường 4','Quận 5','TP. Hồ Chí Minh',NULL,NULL,NULL,1190000.00,650000.00,30000.00,0.00,1870000.00,'bank_transfer','paid',0,NULL,NULL,4,'2026-06-20 12:26:20','2026-06-21 12:26:20',NULL,NULL,'2026-06-20 12:26:20','2026-06-22 12:26:20'),(8,27,NULL,'LMN-ORD-004','available','pending','Nguyễn Văn Mua Sắm','customer2@lumina.vn','0987654321','456 Lê Lợi','Phường Bến Thành','Quận 1','TP. Hồ Chí Minh',NULL,NULL,NULL,990000.00,0.00,30000.00,0.00,1020000.00,'cod','unpaid',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-22 08:26:20','2026-06-22 12:26:20'),(9,27,NULL,'LMN-ORD-005','available','cancelled','Nguyễn Văn Mua Sắm','customer2@lumina.vn','0987654321','456 Lê Lợi','Phường Bến Thành','Quận 1','TP. Hồ Chí Minh',NULL,NULL,NULL,890000.00,0.00,30000.00,0.00,920000.00,'cod','unpaid',0,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-21 12:26:20','2026-06-20 12:26:20','2026-06-22 12:26:20'),(10,18,NULL,'LM260622200827224','available','completed','văn thắng','truc@gmail.com','0769412991','phường 17 bình thạnh','phường 13','quận bình thạnh','sài gòn','008877','--- Đơn kính: hồ sơ 1\r\nOD: SPH +0.75 CYL +10.00 AXIS 33°\r\nOS: SPH +3.00 CYL +10.00 AXIS 123°\r\nPD: 65.0 mm',NULL,3200000.00,0.00,0.00,0.00,3200000.00,'vnpay','unpaid',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-22 13:08:27','2026-06-22 13:11:30'),(11,18,NULL,'LM260622200908602','return_order','pending','văn thắng','truc@gmail.com','0769412991','phường 17 bình thạnh','phường 13','quận bình thạnh','sài gòn','008877','--- Đơn kính: hồ sơ 1\r\nOD: SPH +0.75 CYL +10.00 AXIS 33°\r\nOS: SPH +3.00 CYL +10.00 AXIS 123°\r\nPD: 65.0 mm',NULL,3200000.00,0.00,0.00,0.00,3200000.00,'cod','paid',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-22 13:09:08','2026-07-04 09:02:28'),(12,18,NULL,'LM260625102008515','available','cancelled','văn thắng','truc@gmail.com','0769412991','phường 17 bình thạnh','phường 13','quận bình thạnh','sài gòn','008877',NULL,NULL,1900000.00,0.00,0.00,0.00,1900000.00,'vnpay','unpaid',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-25 03:20:08','2026-07-04 08:52:21'),(13,18,NULL,'LM260625102055380','available','pending','văn thắng','truc@gmail.com','0769412991','phường 17 bình thạnh','phường 13','quận bình thạnh','sài gòn','008877',NULL,NULL,1900000.00,0.00,0.00,0.00,1900000.00,'cod','unpaid',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-25 03:20:55','2026-06-25 03:20:55'),(14,18,NULL,'LM260625102349377','available','completed','văn thắng','truc@gmail.com','0769412991','phường 17 bình thạnh','phường 13','quận bình thạnh','sài gòn','008877',NULL,NULL,680000.00,0.00,0.00,0.00,680000.00,'vnpay','paid',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-25 03:23:49','2026-06-25 03:24:58'),(15,18,NULL,'LM260701215622664','available','cancelled','văn thắng','truc@gmail.com','0769412991','phường 17 bình thạnh','phường 13','quận bình thạnh','sài gòn','008877',NULL,NULL,680000.00,0.00,0.00,0.00,680000.00,'cod','unpaid',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-01 14:56:22','2026-07-01 14:56:32'),(16,18,19,'LM260701215725236','available','completed','văn thắng','truc@gmail.com','0769412991','phường 17 bình thạnh','phường 13','quận bình thạnh','sài gòn','008877',NULL,NULL,1780000.00,0.00,0.00,0.00,1780000.00,'cod','unpaid',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-01 14:57:25','2026-07-01 14:57:44');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prescriptions`
--

DROP TABLE IF EXISTS `prescriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prescriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `prescription_name` varchar(150) DEFAULT NULL,
  `right_sphere` decimal(5,2) DEFAULT NULL,
  `left_sphere` decimal(5,2) DEFAULT NULL,
  `right_cylinder` decimal(5,2) DEFAULT NULL,
  `left_cylinder` decimal(5,2) DEFAULT NULL,
  `right_axis` smallint DEFAULT NULL,
  `left_axis` smallint DEFAULT NULL,
  `right_addition` decimal(5,2) DEFAULT NULL,
  `left_addition` decimal(5,2) DEFAULT NULL,
  `pd_distance` decimal(5,2) DEFAULT NULL,
  `pd_near` decimal(5,2) DEFAULT NULL,
  `prism` varchar(100) DEFAULT NULL,
  `note` text,
  `attachment_path` varchar(255) DEFAULT NULL,
  `verified_by` bigint unsigned DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `verification_status` enum('pending','approved','rejected','needs_clarification') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_prescriptions_verified_by` (`verified_by`),
  KEY `idx_prescriptions_user` (`user_id`),
  KEY `idx_prescriptions_status` (`verification_status`),
  CONSTRAINT `fk_prescriptions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_prescriptions_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prescriptions`
--

LOCK TABLES `prescriptions` WRITE;
/*!40000 ALTER TABLE `prescriptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `prescriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_images`
--

DROP TABLE IF EXISTS `product_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_images` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `variant_id` bigint unsigned DEFAULT NULL,
  `image_url` varchar(255) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_images_product` (`product_id`),
  KEY `idx_product_images_variant` (`variant_id`),
  CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_images_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_images`
--

LOCK TABLES `product_images` WRITE;
/*!40000 ALTER TABLE `product_images` DISABLE KEYS */;
INSERT INTO `product_images` VALUES (1,1,NULL,'/assets/images/products/air-oval-1.jpg','LUMINA Air Oval',1,1,'2026-06-01 08:37:26'),(2,2,NULL,'/assets/images/products/cat-eye-grace-1.jpg','LUMINA Cat Eye Grace',1,1,'2026-06-01 08:37:26'),(3,3,NULL,'/assets/images/products/sun-voyager-1.jpg','LUMINA Sun Voyager',1,1,'2026-06-01 08:37:26');
/*!40000 ALTER TABLE `product_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_lens_options`
--

DROP TABLE IF EXISTS `product_lens_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_lens_options` (
  `product_id` bigint unsigned NOT NULL,
  `lens_option_id` bigint unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`,`lens_option_id`),
  KEY `fk_product_lens_option` (`lens_option_id`),
  CONSTRAINT `fk_product_lens_option` FOREIGN KEY (`lens_option_id`) REFERENCES `lens_options` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_lens_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_lens_options`
--

LOCK TABLES `product_lens_options` WRITE;
/*!40000 ALTER TABLE `product_lens_options` DISABLE KEYS */;
INSERT INTO `product_lens_options` VALUES (1,1,'2026-06-01 08:37:26'),(1,2,'2026-06-01 08:37:26'),(2,1,'2026-06-01 08:37:26'),(2,2,'2026-06-01 08:37:26');
/*!40000 ALTER TABLE `product_lens_options` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_reviews`
--

DROP TABLE IF EXISTS `product_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned NOT NULL COMMENT '????n h??ng ???? completed cho ph??p ????nh gi??',
  `rating` tinyint unsigned NOT NULL DEFAULT '5' COMMENT '1-5 sao',
  `title` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `images` json DEFAULT NULL COMMENT 'M???ng t???i ??a 3 ???????ng d???n ???nh',
  `status` enum('pending','approved','hidden') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `admin_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `helpful_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_review_user_product` (`user_id`,`product_id`),
  KEY `fk_review_order` (`order_id`),
  KEY `idx_review_product` (`product_id`),
  KEY `idx_review_status` (`status`),
  KEY `idx_review_rating` (`rating`),
  KEY `idx_review_created` (`created_at`),
  CONSTRAINT `fk_review_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_review_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_review_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_reviews`
--

LOCK TABLES `product_reviews` WRITE;
/*!40000 ALTER TABLE `product_reviews` DISABLE KEYS */;
INSERT INTO `product_reviews` VALUES (2,23,1,5,5,'Gọng kính rất nhẹ và đẹp!','Kính đeo rất êm tai, không bị đau hay cấn mút thái dương. Tròng chống ánh sáng xanh đi kèm cắt chuẩn độ cận, đeo làm việc máy tính cả ngày không mỏi mắt. Cực kì hài lòng, sẽ ủng hộ Lumina tiếp!','[\"/assets/images/reviews/oval-review-1.jpg\"]','approved',NULL,4,'2026-06-20 12:26:20','2026-06-22 12:26:20'),(3,27,3,6,4,'Kính mát xịn xò, đóng gói cẩn thận','Chất lượng nhựa TR90 của kính khá cứng cáp, mang đi đường xa chống chói cực tốt. Đóng gói hộp rất sang trọng có kèm khăn lau và bao da. Trừ 1 sao vì bưu tá giao hàng hơi trễ tí.','[]','approved',NULL,1,'2026-06-21 12:26:20','2026-06-22 12:26:20'),(4,18,13,14,5,NULL,'okkkkkk',NULL,'approved',NULL,0,'2026-06-25 03:25:50','2026-06-25 03:25:50'),(5,18,7,11,5,NULL,'okkkkk',NULL,'approved',NULL,0,'2026-07-01 14:56:03','2026-07-01 14:56:03');
/*!40000 ALTER TABLE `product_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_variants`
--

DROP TABLE IF EXISTS `product_variants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_variants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `sku` varchar(100) NOT NULL,
  `color` varchar(80) DEFAULT NULL,
  `size_label` varchar(50) DEFAULT NULL,
  `width_mm` decimal(6,2) DEFAULT NULL,
  `bridge_mm` decimal(6,2) DEFAULT NULL,
  `temple_length_mm` decimal(6,2) DEFAULT NULL,
  `material` varchar(100) DEFAULT NULL,
  `price` decimal(12,2) NOT NULL,
  `stock_quantity` int NOT NULL DEFAULT '0',
  `reorder_level` int NOT NULL DEFAULT '0',
  `is_preorder_allowed` tinyint(1) NOT NULL DEFAULT '0',
  `estimated_arrival_date` date DEFAULT NULL,
  `weight_grams` decimal(8,2) DEFAULT NULL,
  `image_override` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`),
  KEY `idx_variants_product_id` (`product_id`),
  KEY `idx_variants_stock` (`stock_quantity`),
  KEY `idx_variants_preorder` (`is_preorder_allowed`),
  CONSTRAINT `fk_variants_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_variants`
--

LOCK TABLES `product_variants` WRITE;
/*!40000 ALTER TABLE `product_variants` DISABLE KEYS */;
INSERT INTO `product_variants` VALUES (1,1,'LMAIR-OVAL-BLK-M','Đen','M',134.00,18.00,140.00,'Kim loại',890000.00,10,3,1,'2026-06-11',NULL,NULL,1,'2026-06-01 08:37:26','2026-06-01 08:37:26'),(2,1,'LMAIR-OVAL-GLD-M','Vàng','M',134.00,18.00,140.00,'Kim loại',920000.00,0,3,1,'2026-06-15',NULL,NULL,1,'2026-06-01 08:37:26','2026-06-01 08:37:26'),(3,2,'LMCAT-GRACE-BRW-M','Nâu','M',136.00,17.00,142.00,'Nhựa acetate',1190000.00,6,2,0,NULL,NULL,NULL,1,'2026-06-01 08:37:26','2026-06-01 08:37:26'),(4,3,'LMSUN-VOYAGER-BLK-L','Đen','L',142.00,20.00,145.00,'TR90',990000.00,12,4,0,NULL,NULL,NULL,1,'2026-06-01 08:37:26','2026-06-01 08:37:26'),(5,6,'LMTI-LITE-SIL-M','Bạc','M',135.00,18.00,140.00,'Titanium',1850000.00,15,3,0,NULL,NULL,NULL,1,'2026-06-22 12:48:37','2026-06-22 12:48:37'),(6,7,'LMCAT-GRACE-BLK-M','Đen vàng','M',136.00,17.00,142.00,'Nhựa Acetate',950000.00,20,5,0,NULL,NULL,NULL,1,'2026-06-22 12:48:37','2026-06-22 12:48:37'),(7,8,'LMAVI-CLASSIC-gld-L','Gọng vàng tròng xanh','L',140.00,14.00,135.00,'Hợp kim',1250000.00,10,2,0,NULL,NULL,NULL,1,'2026-06-22 12:48:37','2026-06-22 12:48:37'),(8,9,'LMWAY-RETRO-BLK-M','Đen nhám','M',142.00,18.00,140.00,'Nhựa dẻo TR90',790000.00,30,5,0,NULL,NULL,NULL,1,'2026-06-22 12:48:37','2026-06-22 12:48:37'),(9,10,'LMOVER-LADY-DM-F','Đồi mồi','F',145.00,20.00,145.00,'Nhựa Acetate',1450000.00,8,2,0,NULL,NULL,NULL,1,'2026-06-22 12:48:37','2026-06-22 12:48:37'),(10,11,'LENS-ESS-ROCK-156','Trong suốt','Standard',NULL,NULL,NULL,NULL,890000.00,100,10,0,NULL,NULL,NULL,1,'2026-06-22 12:48:37','2026-06-22 12:48:37'),(11,12,'LENS-ZEI-BG-160','Trong suốt','Standard',NULL,NULL,NULL,NULL,1490000.00,50,5,0,NULL,NULL,NULL,1,'2026-06-22 12:48:37','2026-06-22 12:48:37'),(12,13,'LENS-CHE-U2-167','Trong suốt','Standard',NULL,NULL,NULL,NULL,680000.00,80,10,0,NULL,NULL,NULL,1,'2026-06-22 12:48:37','2026-06-22 12:48:37');
/*!40000 ALTER TABLE `product_variants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `name` varchar(180) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `brand` varchar(120) DEFAULT NULL,
  `short_description` varchar(255) DEFAULT NULL,
  `description` text,
  `frame_type` varchar(100) DEFAULT NULL,
  `target_gender` enum('male','female','unisex','kids') NOT NULL DEFAULT 'unisex',
  `material` varchar(100) DEFAULT NULL,
  `shape` varchar(100) DEFAULT NULL,
  `default_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `compare_at_price` decimal(12,2) DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `is_prescription_supported` tinyint(1) NOT NULL DEFAULT '0',
  `has_3d_model` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('draft','active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `fk_products_created_by` (`created_by`),
  KEY `fk_products_updated_by` (`updated_by`),
  KEY `idx_products_category_id` (`category_id`),
  KEY `idx_products_name` (`name`),
  KEY `idx_products_brand` (`brand`),
  KEY `idx_products_status` (`status`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_products_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_products_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,5,NULL,NULL,'LUMINA Air Oval','lumina-air-oval','LUMINA','Gọng kính oval tối giản cho dân văn phòng','Mẫu gọng nhẹ, phù hợp cắt tròng cận và chống ánh sáng xanh.','Oval','unisex','Kim loại','Oval',890000.00,NULL,'/assets/images/products/air-oval.jpg',1,0,'active','2026-06-01 08:37:26','2026-06-01 08:37:26'),(2,6,NULL,NULL,'LUMINA Cat Eye Grace','lumina-cat-eye-grace','LUMINA','Gọng mắt mèo thanh lịch dành cho nữ','Dòng gọng nhẹ, thời trang, phù hợp lắp nhiều loại tròng.','Cat Eye','female','Nhựa acetate','Cat Eye',1190000.00,NULL,'/assets/images/products/cat-eye-grace.jpg',1,0,'active','2026-06-01 08:37:26','2026-06-01 08:37:26'),(3,8,NULL,NULL,'LUMINA Sun Voyager Modified','lumina-sun-voyager','LUMINA','Kính mát unisex phong cách du lịch','Mẫu kính mát phù hợp đi đường và du lịch.','Sunglasses','unisex','TR90','Square',990000.00,NULL,'/assets/images/products/sun-voyager.jpg',0,0,'active','2026-06-01 08:37:26','2026-06-04 04:30:02'),(4,1,NULL,NULL,'Kinh mat mua he 2026','kinh-mat-mua-he-2026','LUMINA','','','','unisex','','',650000.00,NULL,'',0,0,'active','2026-06-04 04:38:19','2026-06-04 04:39:35'),(5,1,NULL,NULL,'Classic Round','lumina-classic-round','LUMINA','Gọng kính tròn thanh mảnh mang phong cách Hàn Quốc retro.','Thiết kế tối giản tinh tế, chất liệu kim loại chống hoen gỉ, đuôi kính bọc đệm nhựa êm ái khi đeo. Thích hợp cho học sinh, sinh viên và nhân viên văn phòng.\r\nFrame type: Round (Tròn)','Round (Tròn)','unisex','Kim loại không gỉ','Round (Tròn)',650000.00,800000.00,'/assets/images/products/5351167b05c5d78071009913fce41262.webp',1,0,'active','2026-06-22 12:44:25','2026-06-22 12:44:25'),(6,4,NULL,NULL,'LUMINA Titanium Lite','lumina-titanium-lite','LUMINA','Gọng kính Titanium không viền cao cấp, siêu nhẹ và sang trọng.','Sử dụng chất liệu Titanium siêu nhẹ (chỉ nặng khoảng 8g), không gây dị ứng da và chống ăn mòn cực tốt. Thiết kế tối giản lịch lãm, phù hợp cho doanh nhân.','Rimless','unisex','Titanium','Rectangle',1850000.00,NULL,'/assets/images/products/f6c1c343c511a3bf7c88e0c5b75fca11.webp',1,0,'active','2026-06-22 12:48:37','2026-06-22 12:54:34'),(7,6,NULL,NULL,'LUMINA Cat Eye Grace V2','lumina-cat-eye-grace-v2','LUMINA','Gọng mắt mèo thời trang tôn lên vẻ sang trọng cho phái nữ.','Sự kết hợp hoàn hảo giữa viền nhựa Acetate đen bóng và càng kính kim loại mạ vàng sang trọng. Thiết kế thanh thoát giúp khuôn mặt nữ giới trông thon gọn và cuốn hút hơn.','Cat Eye','female','Nhựa và Kim loại','Cat Eye',950000.00,NULL,'/assets/images/products/b63f8092ebee6bbe8e49c27b61ef6505.webp',1,0,'active','2026-06-22 12:48:37','2026-06-22 12:54:02'),(8,8,NULL,NULL,'LUMINA Aviator Classic','lumina-aviator-classic','LUMINA','Kính mát dáng phi công kinh điển với tròng chống tia UV400.','Thiết kế gọng đôi độc đáo, mắt kính màu xanh rau muống chống chói cực tốt khi lái xe hoặc đi nắng. Bản lề chắc chắn, đệm mũi silicone êm ái có thể tự điều chỉnh.','Aviator','unisex','Hợp kim','Teardrop',1250000.00,NULL,'/assets/images/products/a98bbd23d819b29d0d3188e4d86a8113.webp',0,0,'active','2026-06-22 12:48:37','2026-06-22 12:53:32'),(9,8,NULL,NULL,'LUMINA Wayfarer Retro','lumina-wayfarer-retro','LUMINA','Kính mát thể thao gọng dẻo TR90 chống tia cực tím.','Sử dụng gọng nhựa dẻo TR90 chịu lực cực tốt, siêu nhẹ, không bị gãy gập khi vận động thể thao mạnh. Tròng phân cực Polarized chống lóa hoàn hảo khi di chuyển ngoài trời.','Wayfarer','male','Nhựa dẻo TR90','Square',790000.00,NULL,'/assets/images/products/0d8865aa6f772f1038917133b1bced3a.webp',0,0,'active','2026-06-22 12:48:37','2026-06-22 12:52:59'),(10,9,NULL,NULL,'LUMINA Oversized Lady','lumina-oversized-lady','LUMINA','Kính mát gọng to sang chảnh phong cách ngôi sao điện ảnh.','Tròng kính chuyển màu khói thời thượng chống tia UV tốt bảo vệ đôi mắt tối đa. Gọng nhựa Acetate bóng bẩy kết hợp họa tiết đồi mồi sang trọng, thời thượng.','Oversized','female','Nhựa Acetate','Round',1450000.00,NULL,'/assets/images/products/d865fd30c21d0862a165ac4ddd547898.webp',0,0,'active','2026-06-22 12:48:37','2026-06-22 12:52:37'),(11,11,NULL,NULL,'Tròng Essilor Crizal Rock 1.56','trong-essilor-crizal-rock-156','Essilor','Tròng kính siêu chống trầy xước và hạn chế bám bụi bẩn nước mưa.','Công nghệ phủ Crizal Rock giúp tăng cường độ cứng gấp 3 lần so với tròng thông thường. Hạn chế tối đa bám nước, bám bụi bẩn, dấu vân tay và chống chói khi lái xe đêm.','None','unisex','Nhựa CR39','Circular',890000.00,NULL,'/assets/images/products/dfd66d2ec7b1ef0a79949619135342f5.webp',0,0,'active','2026-06-22 12:48:37','2026-06-22 12:52:04'),(12,11,NULL,NULL,'Tròng Zeiss BlueGuard 1.60','trong-zeiss-blueguard-160','Zeiss','Tròng kính bảo vệ mắt trước ánh sáng xanh có hại từ màn hình thiết bị điện tử.','Zeiss BlueGuard lọc ánh sáng xanh tích hợp sâu vào chất liệu tròng kính giúp ngăn cản tới 40% ánh sáng xanh có hại nhưng không bị ngả vàng gây mất thẩm mỹ. Độ trong suốt hoàn hảo giúp nhìn màu sắc trung thực nhất.','None','unisex','Nhựa 1.60','Circular',1490000.00,NULL,'/assets/images/products/aeadb1afcb21ef8441ce409eb3f6e0ed.webp',0,0,'active','2026-06-22 12:48:37','2026-06-22 12:51:29'),(13,12,NULL,NULL,'Tròng Chemi U2 1.67','trong-chemi-u2-167','Chemi','Tròng kính chiết suất mỏng 1.67 lý tưởng cho người cận từ 4 độ trở lên.','Tròng kính mỏng và nhẹ hơn tròng thông thường tới 30-35%, giảm hiệu ứng rìa kính dày. Công nghệ lớp phủ Crystal U2 chống tĩnh điện, hạn chế bám bụi bẩn và vân tay cực tốt.','None','unisex','Nhựa 1.67','Circular',680000.00,NULL,'/assets/images/products/dd795d9bfac44af70e01b8011a2d0843.webp',0,0,'active','2026-06-22 12:48:37','2026-06-22 12:50:31');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `return_requests`
--

DROP TABLE IF EXISTS `return_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `return_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `handled_by` bigint unsigned DEFAULT NULL,
  `reason` text NOT NULL,
  `request_type` enum('return','exchange','warranty','refund') NOT NULL DEFAULT 'return',
  `status` enum('pending','approved','rejected','received','resolved') NOT NULL DEFAULT 'pending',
  `resolution_note` text,
  `images` json DEFAULT NULL COMMENT 'Mảng tối đa 3 đường dẫn ảnh',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_return_requests_handled_by` (`handled_by`),
  KEY `idx_return_requests_order_id` (`order_id`),
  KEY `idx_return_requests_user_id` (`user_id`),
  KEY `idx_return_requests_status` (`status`),
  CONSTRAINT `fk_return_requests_handled_by` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_return_requests_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_return_requests_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `return_requests`
--

LOCK TABLES `return_requests` WRITE;
/*!40000 ALTER TABLE `return_requests` DISABLE KEYS */;
INSERT INTO `return_requests` VALUES (2,5,23,NULL,'Tôi muốn đổi sang gọng màu Vàng cùng loại vì đeo thử thấy màu đen không hợp với khuôn mặt lắm.','exchange','pending',NULL,'[\"/assets/images/returns/return-request-1.jpg\"]','2026-06-22 00:26:20','2026-06-22 12:26:20'),(3,14,18,NULL,'okkkkkkk','exchange','pending',NULL,NULL,'2026-06-25 03:26:07','2026-06-25 03:26:07'),(4,11,18,28,'OKKKKKK','exchange','approved','okkkkkk',NULL,'2026-06-25 03:59:12','2026-07-04 09:02:28'),(5,16,18,19,'k okkk','return','rejected','','[\"/assets/uploads/returns/ret_18_16_1782917991_0.png\"]','2026-07-01 14:59:51','2026-07-01 15:00:05');
/*!40000 ALTER TABLE `return_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'customer','Customer / buyer','2026-06-01 08:37:26','2026-06-01 08:37:26'),(2,'sales','Sales and support staff','2026-06-01 08:37:26','2026-06-01 08:37:26'),(3,'operations','Operations staff','2026-06-01 08:37:26','2026-06-01 08:37:26'),(4,'manager','Business manager','2026-06-01 08:37:26','2026-06-01 08:37:26'),(5,'admin','System administrator','2026-06-01 08:37:26','2026-06-01 08:37:26');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint unsigned NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `address_line` varchar(255) DEFAULT NULL,
  `ward` varchar(120) DEFAULT NULL,
  `district` varchar(120) DEFAULT NULL,
  `province` varchar(120) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive','banned') NOT NULL DEFAULT 'active',
  `email_verified_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_role_id` (`role_id`),
  KEY `idx_users_full_name` (`full_name`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (13,1,'văn thắng','thang@gmail.com','0769412991','.JWuWTi5TvCo2FxZkq9X9hBDoWrssADOsF6',NULL,NULL,NULL,'hcm','jhj','bhhb','jhjh','jhjh','active',NULL,NULL,'2026-06-04 06:31:08','2026-06-16 14:20:55'),(14,1,'êr','th@gmail.com','êrer','.JWuWTi5TvCo2FxZkq9X9hBDoWrssADOsF6',NULL,NULL,NULL,'êr','ể','êr','rể','êre','active',NULL,NULL,'2026-06-04 15:30:51','2026-06-16 14:20:55'),(15,1,'Thắng Trương Văn','thangtv5280@ut.edu.vn','0768987665','.JWuWTi5TvCo2FxZkq9X9hBDoWrssADOsF6',NULL,NULL,NULL,'sdsd','ể','êr','sdsd','sdsd','active',NULL,NULL,'2026-06-04 15:34:57','2026-06-17 14:41:35'),(17,1,'văn thắng','cus@gmail.com','0769412991','.JWuWTi5TvCo2FxZkq9X9hBDoWrssADOsF6',NULL,NULL,NULL,'hcm','jhj','bhhb','jhjh','jhjh','active',NULL,NULL,'2026-06-16 14:14:51','2026-06-16 14:20:55'),(18,1,'văn thắng','truc@gmail.com','0769412991','$2y$10$LsGbgIcx0aydWV9n.09DVeLZunM4Rx.JhWvMGlRYunGmAHNd3GpdK',NULL,NULL,NULL,'phường 17 bình thạnh','phường 13','quận bình thạnh','sài gòn','008877','active',NULL,NULL,'2026-06-16 14:27:47','2026-07-01 14:57:25'),(19,5,'Admin LUMINA','admin@lumina.vn','0900000001','$2y$12$wsyBV6sDzU/Le1bQZwkHxesAWPhP/lPO.dnWVAKRi27EtnNli.smS',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'active','2026-06-17 13:46:35',NULL,'2026-06-17 13:46:35','2026-06-17 13:46:35'),(20,4,'Nguyễn Văn Manager','manager@lumina.vn','0900000002','$2y$12$wsyBV6sDzU/Le1bQZwkHxesAWPhP/lPO.dnWVAKRi27EtnNli.smS',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'active','2026-06-17 13:46:35',NULL,'2026-06-17 13:46:35','2026-06-17 13:46:35'),(21,2,'Trần Thị Sales','sales@lumina.vn','0900000003','$2y$12$wsyBV6sDzU/Le1bQZwkHxesAWPhP/lPO.dnWVAKRi27EtnNli.smS',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'active','2026-06-17 13:46:35',NULL,'2026-06-17 13:46:35','2026-06-17 13:46:35'),(22,3,'Lê Văn Operations','operations@lumina.vn','0900000004','$2y$12$wsyBV6sDzU/Le1bQZwkHxesAWPhP/lPO.dnWVAKRi27EtnNli.smS',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'active','2026-06-17 13:46:35',NULL,'2026-06-17 13:46:35','2026-06-17 13:46:35'),(23,1,'Phạm Thị Khách Hàng','customer1@lumina.vn','0912345678','$2y$12$wsyBV6sDzU/Le1bQZwkHxesAWPhP/lPO.dnWVAKRi27EtnNli.smS',NULL,'female','1995-06-15','123 Nguyễn Văn Cừ',NULL,'Quận 5','TP. Hồ Chí Minh',NULL,'active','2026-06-17 13:46:35',NULL,'2026-06-17 13:46:35','2026-06-17 13:46:35'),(25,1,'Thang Truong','truongthang31102115@gmail.com','33333333','$2y$10$1pgeDTEV2/NH6AEbw939nujzS.YbtRLQAHfqj9ngvm7Ya9Kc9SWD.',NULL,NULL,NULL,'3232ưewe','ewwew','ưewe','ưewe','ưewe','active',NULL,NULL,'2026-06-18 08:18:44','2026-06-19 07:32:05'),(26,1,'ty','truongthang102115@gmail.com','0987654332','$2y$10$6l0pPecIp1s.18Hzk7GJPOXc5qBTA3cYFwYAuozr.BBVxPVgMHCym',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'2026-06-19 07:46:47','2026-06-19 07:46:47'),(27,1,'Nguyễn Văn Mua Sắm','customer2@lumina.vn','0987654321','$2y$12$wsyBV6sDzU/Le1bQZwkHxesAWPhP/lPO.dnWVAKRi27EtnNli.smS',NULL,'male','1998-03-22','456 Lê Lợi',NULL,'Quận 1','TP. Hồ Chí Minh',NULL,'active','2026-06-22 12:26:20',NULL,'2026-06-22 12:26:20','2026-06-22 12:26:20'),(28,4,'Thắng Trương Văn','customer1@example.com','0769412992','$2y$10$/Qiaf13HQZ5KBqi0C7zBde8ReW7lRJ6bFhay6NsPzJ/4ACCU5GaBm',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,'2026-07-04 08:53:02','2026-07-04 08:53:17');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-04  9:13:23
