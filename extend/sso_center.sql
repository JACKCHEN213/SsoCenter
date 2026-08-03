-- MariaDB dump 10.19  Distrib 10.8.3-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: sso_center
-- ------------------------------------------------------
-- Server version	10.8.3-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT = @@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS = @@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION = @@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE = @@TIME_ZONE */;
/*!40103 SET TIME_ZONE = '+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS = @@UNIQUE_CHECKS, UNIQUE_CHECKS = 0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS = 0 */;
/*!40101 SET @OLD_SQL_MODE = @@SQL_MODE, SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES = @@SQL_NOTES, SQL_NOTES = 0 */;

--
-- Current Database: `sso_center`
--

/*!40000 DROP DATABASE IF EXISTS `sso_center`*/;

CREATE
    DATABASE /*!32312 IF NOT EXISTS */ `sso_center` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE
    `sso_center`;

--
-- Table structure for table `sc_login_history`
--

DROP TABLE IF EXISTS `sc_login_history`;
/*!40101 SET @saved_cs_client = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sc_login_history`
(
    `id`         int(11) NOT NULL AUTO_INCREMENT,
    `username`   varchar(255) DEFAULT NULL,
    `ip`         varchar(255) DEFAULT NULL,
    `login_time` datetime     DEFAULT NULL,
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB
  AUTO_INCREMENT = 1
  DEFAULT CHARSET = utf8mb4
  ROW_FORMAT = DYNAMIC COMMENT ='登录记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sc_login_history`
--

LOCK
    TABLES `sc_login_history` WRITE;
/*!40000 ALTER TABLE `sc_login_history`
    DISABLE KEYS */;
INSERT INTO `sc_login_history`
VALUES
/*!40000 ALTER TABLE `sc_login_history`
    ENABLE KEYS */;
UNLOCK
    TABLES;

--
-- Table structure for table `sc_site`
--

DROP TABLE IF EXISTS `sc_site`;
/*!40101 SET @saved_cs_client = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sc_site`
(
    `id`           int(11) NOT NULL AUTO_INCREMENT,
    `name`         varchar(255) DEFAULT NULL,
    `image`        varchar(255) DEFAULT NULL,
    `request_url`  varchar(255) DEFAULT NULL,
    `redirect_url` varchar(255) DEFAULT NULL,
    `is_use`       tinyint(4)   DEFAULT 1,
    `is_del`       tinyint(4)   DEFAULT 0,
    `public_key`   varchar(255) DEFAULT NULL,
    `app_path`     varchar(255) DEFAULT NULL,
    `create_time`  datetime     DEFAULT current_timestamp(),
    `update_time`  datetime     DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  ROW_FORMAT = DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sc_site`
--

LOCK
    TABLES `sc_site` WRITE;
/*!40000 ALTER TABLE `sc_site`
    DISABLE KEYS */;
/*!40000 ALTER TABLE `sc_site`
    ENABLE KEYS */;
UNLOCK
    TABLES;

--
-- Table structure for table `sc_user`
--

DROP TABLE IF EXISTS `sc_user`;
/*!40101 SET @saved_cs_client = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sc_user`
(
    `id`          int(11) NOT NULL AUTO_INCREMENT COMMENT '自增id',
    `username`    varchar(255) DEFAULT NULL,
    `password`    varchar(255) DEFAULT NULL,
    `create_time` datetime     DEFAULT current_timestamp(),
    `update_time` datetime     DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    `email`       varchar(255) DEFAULT NULL,
    `status`      tinyint(4)   DEFAULT 1 COMMENT '1',
    `type`        int(11)      DEFAULT 2 COMMENT '1',
    `ip`          varchar(255) DEFAULT NULL,
    `is_del`      tinyint(4)   DEFAULT 0,
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB
  AUTO_INCREMENT = 1
  DEFAULT CHARSET = utf8mb4
  ROW_FORMAT = DYNAMIC COMMENT ='用户表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sc_user`
--

LOCK
    TABLES `sc_user` WRITE;
/*!40000 ALTER TABLE `sc_user`
    DISABLE KEYS */;
INSERT INTO `sc_user`
VALUES (1, 'admin', 'c3284d0f94606de1fd2af172aba15bf3', '2022-12-21 10:51:45', '2023-06-03 22:42:33', 'admin@admin', 1,
        1, NULL, 0),
    /*!40000 ALTER TABLE `sc_user` ENABLE KEYS */;
UNLOCK
    TABLES;
/*!40103 SET TIME_ZONE = @OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE = @OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS = @OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT = @OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS = @OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION = @OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES = @OLD_SQL_NOTES */;

-- Dump completed on 2023-06-03 23:36:32
