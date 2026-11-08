-- MySQL dump 10.13  Distrib 8.4.13, for Linux (aarch64)
--
-- Host: localhost    Database: wordpress
-- ------------------------------------------------------
-- Server version	8.4.13

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
-- Table structure for table `wp_commentmeta`
--

DROP TABLE IF EXISTS `wp_commentmeta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_commentmeta` (
  `meta_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `comment_id` bigint unsigned NOT NULL DEFAULT '0',
  `meta_key` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `meta_value` longtext COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`meta_id`),
  KEY `comment_id` (`comment_id`),
  KEY `meta_key` (`meta_key`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_commentmeta`
--

LOCK TABLES `wp_commentmeta` WRITE;
/*!40000 ALTER TABLE `wp_commentmeta` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_commentmeta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_comments`
--

DROP TABLE IF EXISTS `wp_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_comments` (
  `comment_ID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `comment_post_ID` bigint unsigned NOT NULL DEFAULT '0',
  `comment_author` tinytext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `comment_author_email` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `comment_author_url` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `comment_author_IP` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `comment_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `comment_date_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `comment_content` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `comment_karma` int NOT NULL DEFAULT '0',
  `comment_approved` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '1',
  `comment_agent` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `comment_type` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'comment',
  `comment_parent` bigint unsigned NOT NULL DEFAULT '0',
  `user_id` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`comment_ID`),
  KEY `comment_post_ID` (`comment_post_ID`),
  KEY `comment_approved_date_gmt` (`comment_approved`,`comment_date_gmt`),
  KEY `comment_date_gmt` (`comment_date_gmt`),
  KEY `comment_parent` (`comment_parent`),
  KEY `comment_author_email` (`comment_author_email`(10))
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_comments`
--

LOCK TABLES `wp_comments` WRITE;
/*!40000 ALTER TABLE `wp_comments` DISABLE KEYS */;
INSERT INTO `wp_comments` VALUES (2,6,'Visitor','visitor@example.com','https://visitor.example','','2026-01-11 08:00:00','2026-01-11 07:00:00','Great read!',0,'1','','comment',0,0),(3,6,'Eda Editor','editor@example.com','','','2026-01-11 09:00:00','2026-01-11 08:00:00','Thank you!',0,'1','','comment',2,2),(4,10,'Quiet','quiet@example.com','','','2026-02-15 10:00:00','2026-02-15 09:00:00','Waiting for approval',0,'0','','comment',0,0),(5,10,'Spammer','spam@example.com','','','2026-02-16 10:00:00','2026-02-16 09:00:00','Buy now',0,'spam','','comment',0,0);
/*!40000 ALTER TABLE `wp_comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_links`
--

DROP TABLE IF EXISTS `wp_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_links` (
  `link_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `link_url` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `link_name` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `link_image` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `link_target` varchar(25) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `link_description` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `link_visible` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'Y',
  `link_owner` bigint unsigned NOT NULL DEFAULT '1',
  `link_rating` int NOT NULL DEFAULT '0',
  `link_updated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `link_rel` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `link_notes` mediumtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `link_rss` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`link_id`),
  KEY `link_visible` (`link_visible`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_links`
--

LOCK TABLES `wp_links` WRITE;
/*!40000 ALTER TABLE `wp_links` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_links` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_options`
--

DROP TABLE IF EXISTS `wp_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_options` (
  `option_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `option_name` varchar(191) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `option_value` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `autoload` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'yes',
  PRIMARY KEY (`option_id`),
  UNIQUE KEY `option_name` (`option_name`),
  KEY `autoload` (`autoload`)
) ENGINE=InnoDB AUTO_INCREMENT=146 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_options`
--

LOCK TABLES `wp_options` WRITE;
/*!40000 ALTER TABLE `wp_options` DISABLE KEYS */;
INSERT INTO `wp_options` VALUES (1,'cron','a:9:{i:1794128730;a:1:{s:32:\"recovery_mode_clean_expired_keys\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:5:\"daily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:86400;}}}i:1794128731;a:3:{s:34:\"wp_privacy_delete_old_export_files\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:6:\"hourly\";s:4:\"args\";a:0:{}s:8:\"interval\";i:3600;}}s:41:\"wp_privacy_personal_data_cleanup_requests\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:5:\"daily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:86400;}}s:30:\"wp_delete_temp_updater_backups\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:6:\"weekly\";s:4:\"args\";a:0:{}s:8:\"interval\";i:604800;}}}i:1794128738;a:1:{s:27:\"acf_update_site_health_data\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:5:\"daily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:86400;}}}i:1794128739;a:1:{s:8:\"do_pings\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:2:{s:8:\"schedule\";b:0;s:4:\"args\";a:0:{}}}}i:1794132330;a:1:{s:16:\"wp_version_check\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:10:\"twicedaily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:43200;}}}i:1794134130;a:1:{s:17:\"wp_update_plugins\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:10:\"twicedaily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:43200;}}}i:1794135930;a:1:{s:16:\"wp_update_themes\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:10:\"twicedaily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:43200;}}}i:1794215130;a:1:{s:30:\"wp_site_health_scheduled_check\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:6:\"weekly\";s:4:\"args\";a:0:{}s:8:\"interval\";i:604800;}}}s:7:\"version\";i:2;}','on'),(2,'siteurl','https://old.example.com','on'),(3,'home','https://old.example.com','on'),(4,'blogname','Acme','on'),(5,'blogdescription','We make things','on'),(6,'users_can_register','0','on'),(7,'admin_email','admin@example.com','on'),(8,'start_of_week','1','on'),(9,'use_balanceTags','0','on'),(10,'use_smilies','1','on'),(11,'require_name_email','1','on'),(12,'comments_notify','1','on'),(13,'posts_per_rss','10','on'),(14,'rss_use_excerpt','0','on'),(15,'mailserver_url','mail.example.com','on'),(16,'mailserver_login','login@example.com','on'),(17,'mailserver_pass','','on'),(18,'mailserver_port','110','on'),(19,'default_category','1','on'),(20,'default_comment_status','open','on'),(21,'default_ping_status','open','on'),(22,'default_pingback_flag','1','on'),(23,'posts_per_page','10','on'),(24,'date_format','d.m.Y','on'),(25,'time_format','g:i a','on'),(26,'links_updated_date_format','F j, Y g:i a','on'),(27,'comment_moderation','0','on'),(28,'moderation_notify','1','on'),(29,'permalink_structure','/%year%/%monthnum%/%postname%/','on'),(30,'rewrite_rules','a:104:{s:11:\"^wp-json/?$\";s:22:\"index.php?rest_route=/\";s:14:\"^wp-json/(.*)?\";s:33:\"index.php?rest_route=/$matches[1]\";s:21:\"^index.php/wp-json/?$\";s:22:\"index.php?rest_route=/\";s:24:\"^index.php/wp-json/(.*)?\";s:33:\"index.php?rest_route=/$matches[1]\";s:17:\"^wp-sitemap\\.xml$\";s:23:\"index.php?sitemap=index\";s:17:\"^wp-sitemap\\.xsl$\";s:36:\"index.php?sitemap-stylesheet=sitemap\";s:23:\"^wp-sitemap-index\\.xsl$\";s:34:\"index.php?sitemap-stylesheet=index\";s:48:\"^wp-sitemap-([a-z]+?)-([a-z\\d_-]+?)-(\\d+?)\\.xml$\";s:75:\"index.php?sitemap=$matches[1]&sitemap-subtype=$matches[2]&paged=$matches[3]\";s:34:\"^wp-sitemap-([a-z]+?)-(\\d+?)\\.xml$\";s:47:\"index.php?sitemap=$matches[1]&paged=$matches[2]\";s:18:\"veranstaltungen/?$\";s:25:\"index.php?post_type=event\";s:48:\"veranstaltungen/feed/(feed|rdf|rss|rss2|atom)/?$\";s:42:\"index.php?post_type=event&feed=$matches[1]\";s:43:\"veranstaltungen/(feed|rdf|rss|rss2|atom)/?$\";s:42:\"index.php?post_type=event&feed=$matches[1]\";s:35:\"veranstaltungen/page/([0-9]{1,})/?$\";s:43:\"index.php?post_type=event&paged=$matches[1]\";s:43:\"veranstaltungen/[^/]+/attachment/([^/]+)/?$\";s:32:\"index.php?attachment=$matches[1]\";s:53:\"veranstaltungen/[^/]+/attachment/([^/]+)/trackback/?$\";s:37:\"index.php?attachment=$matches[1]&tb=1\";s:73:\"veranstaltungen/[^/]+/attachment/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$\";s:49:\"index.php?attachment=$matches[1]&feed=$matches[2]\";s:68:\"veranstaltungen/[^/]+/attachment/([^/]+)/(feed|rdf|rss|rss2|atom)/?$\";s:49:\"index.php?attachment=$matches[1]&feed=$matches[2]\";s:68:\"veranstaltungen/[^/]+/attachment/([^/]+)/comment-page-([0-9]{1,})/?$\";s:50:\"index.php?attachment=$matches[1]&cpage=$matches[2]\";s:49:\"veranstaltungen/[^/]+/attachment/([^/]+)/embed/?$\";s:43:\"index.php?attachment=$matches[1]&embed=true\";s:32:\"veranstaltungen/([^/]+)/embed/?$\";s:38:\"index.php?event=$matches[1]&embed=true\";s:36:\"veranstaltungen/([^/]+)/trackback/?$\";s:32:\"index.php?event=$matches[1]&tb=1\";s:56:\"veranstaltungen/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$\";s:44:\"index.php?event=$matches[1]&feed=$matches[2]\";s:51:\"veranstaltungen/([^/]+)/(feed|rdf|rss|rss2|atom)/?$\";s:44:\"index.php?event=$matches[1]&feed=$matches[2]\";s:44:\"veranstaltungen/([^/]+)/page/?([0-9]{1,})/?$\";s:45:\"index.php?event=$matches[1]&paged=$matches[2]\";s:51:\"veranstaltungen/([^/]+)/comment-page-([0-9]{1,})/?$\";s:45:\"index.php?event=$matches[1]&cpage=$matches[2]\";s:40:\"veranstaltungen/([^/]+)(?:/([0-9]+))?/?$\";s:44:\"index.php?event=$matches[1]&page=$matches[2]\";s:32:\"veranstaltungen/[^/]+/([^/]+)/?$\";s:32:\"index.php?attachment=$matches[1]\";s:42:\"veranstaltungen/[^/]+/([^/]+)/trackback/?$\";s:37:\"index.php?attachment=$matches[1]&tb=1\";s:62:\"veranstaltungen/[^/]+/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$\";s:49:\"index.php?attachment=$matches[1]&feed=$matches[2]\";s:57:\"veranstaltungen/[^/]+/([^/]+)/(feed|rdf|rss|rss2|atom)/?$\";s:49:\"index.php?attachment=$matches[1]&feed=$matches[2]\";s:57:\"veranstaltungen/[^/]+/([^/]+)/comment-page-([0-9]{1,})/?$\";s:50:\"index.php?attachment=$matches[1]&cpage=$matches[2]\";s:38:\"veranstaltungen/[^/]+/([^/]+)/embed/?$\";s:43:\"index.php?attachment=$matches[1]&embed=true\";s:12:\"robots\\.txt$\";s:18:\"index.php?robots=1\";s:13:\"favicon\\.ico$\";s:19:\"index.php?favicon=1\";s:12:\"sitemap\\.xml\";s:23:\"index.php?sitemap=index\";s:48:\".*wp-(atom|rdf|rss|rss2|feed|commentsrss2)\\.php$\";s:18:\"index.php?feed=old\";s:20:\".*wp-app\\.php(/.*)?$\";s:19:\"index.php?error=403\";s:18:\".*wp-register.php$\";s:23:\"index.php?register=true\";s:32:\"feed/(feed|rdf|rss|rss2|atom)/?$\";s:27:\"index.php?&feed=$matches[1]\";s:27:\"(feed|rdf|rss|rss2|atom)/?$\";s:27:\"index.php?&feed=$matches[1]\";s:8:\"embed/?$\";s:21:\"index.php?&embed=true\";s:20:\"page/?([0-9]{1,})/?$\";s:28:\"index.php?&paged=$matches[1]\";s:41:\"comments/feed/(feed|rdf|rss|rss2|atom)/?$\";s:42:\"index.php?&feed=$matches[1]&withcomments=1\";s:36:\"comments/(feed|rdf|rss|rss2|atom)/?$\";s:42:\"index.php?&feed=$matches[1]&withcomments=1\";s:17:\"comments/embed/?$\";s:21:\"index.php?&embed=true\";s:44:\"search/(.+)/feed/(feed|rdf|rss|rss2|atom)/?$\";s:40:\"index.php?s=$matches[1]&feed=$matches[2]\";s:39:\"search/(.+)/(feed|rdf|rss|rss2|atom)/?$\";s:40:\"index.php?s=$matches[1]&feed=$matches[2]\";s:20:\"search/(.+)/embed/?$\";s:34:\"index.php?s=$matches[1]&embed=true\";s:32:\"search/(.+)/page/?([0-9]{1,})/?$\";s:41:\"index.php?s=$matches[1]&paged=$matches[2]\";s:14:\"search/(.+)/?$\";s:23:\"index.php?s=$matches[1]\";s:47:\"author/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$\";s:50:\"index.php?author_name=$matches[1]&feed=$matches[2]\";s:42:\"author/([^/]+)/(feed|rdf|rss|rss2|atom)/?$\";s:50:\"index.php?author_name=$matches[1]&feed=$matches[2]\";s:23:\"author/([^/]+)/embed/?$\";s:44:\"index.php?author_name=$matches[1]&embed=true\";s:35:\"author/([^/]+)/page/?([0-9]{1,})/?$\";s:51:\"index.php?author_name=$matches[1]&paged=$matches[2]\";s:17:\"author/([^/]+)/?$\";s:33:\"index.php?author_name=$matches[1]\";s:69:\"([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/feed/(feed|rdf|rss|rss2|atom)/?$\";s:80:\"index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&feed=$matches[4]\";s:64:\"([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom)/?$\";s:80:\"index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&feed=$matches[4]\";s:45:\"([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/embed/?$\";s:74:\"index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&embed=true\";s:57:\"([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/page/?([0-9]{1,})/?$\";s:81:\"index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&paged=$matches[4]\";s:39:\"([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/?$\";s:63:\"index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]\";s:56:\"([0-9]{4})/([0-9]{1,2})/feed/(feed|rdf|rss|rss2|atom)/?$\";s:64:\"index.php?year=$matches[1]&monthnum=$matches[2]&feed=$matches[3]\";s:51:\"([0-9]{4})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom)/?$\";s:64:\"index.php?year=$matches[1]&monthnum=$matches[2]&feed=$matches[3]\";s:32:\"([0-9]{4})/([0-9]{1,2})/embed/?$\";s:58:\"index.php?year=$matches[1]&monthnum=$matches[2]&embed=true\";s:44:\"([0-9]{4})/([0-9]{1,2})/page/?([0-9]{1,})/?$\";s:65:\"index.php?year=$matches[1]&monthnum=$matches[2]&paged=$matches[3]\";s:26:\"([0-9]{4})/([0-9]{1,2})/?$\";s:47:\"index.php?year=$matches[1]&monthnum=$matches[2]\";s:43:\"([0-9]{4})/feed/(feed|rdf|rss|rss2|atom)/?$\";s:43:\"index.php?year=$matches[1]&feed=$matches[2]\";s:38:\"([0-9]{4})/(feed|rdf|rss|rss2|atom)/?$\";s:43:\"index.php?year=$matches[1]&feed=$matches[2]\";s:19:\"([0-9]{4})/embed/?$\";s:37:\"index.php?year=$matches[1]&embed=true\";s:31:\"([0-9]{4})/page/?([0-9]{1,})/?$\";s:44:\"index.php?year=$matches[1]&paged=$matches[2]\";s:13:\"([0-9]{4})/?$\";s:26:\"index.php?year=$matches[1]\";s:47:\"[0-9]{4}/[0-9]{1,2}/[^/]+/attachment/([^/]+)/?$\";s:32:\"index.php?attachment=$matches[1]\";s:57:\"[0-9]{4}/[0-9]{1,2}/[^/]+/attachment/([^/]+)/trackback/?$\";s:37:\"index.php?attachment=$matches[1]&tb=1\";s:77:\"[0-9]{4}/[0-9]{1,2}/[^/]+/attachment/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$\";s:49:\"index.php?attachment=$matches[1]&feed=$matches[2]\";s:72:\"[0-9]{4}/[0-9]{1,2}/[^/]+/attachment/([^/]+)/(feed|rdf|rss|rss2|atom)/?$\";s:49:\"index.php?attachment=$matches[1]&feed=$matches[2]\";s:72:\"[0-9]{4}/[0-9]{1,2}/[^/]+/attachment/([^/]+)/comment-page-([0-9]{1,})/?$\";s:50:\"index.php?attachment=$matches[1]&cpage=$matches[2]\";s:53:\"[0-9]{4}/[0-9]{1,2}/[^/]+/attachment/([^/]+)/embed/?$\";s:43:\"index.php?attachment=$matches[1]&embed=true\";s:40:\"([0-9]{4})/([0-9]{1,2})/([^/]+)/embed/?$\";s:75:\"index.php?year=$matches[1]&monthnum=$matches[2]&name=$matches[3]&embed=true\";s:44:\"([0-9]{4})/([0-9]{1,2})/([^/]+)/trackback/?$\";s:69:\"index.php?year=$matches[1]&monthnum=$matches[2]&name=$matches[3]&tb=1\";s:64:\"([0-9]{4})/([0-9]{1,2})/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$\";s:81:\"index.php?year=$matches[1]&monthnum=$matches[2]&name=$matches[3]&feed=$matches[4]\";s:59:\"([0-9]{4})/([0-9]{1,2})/([^/]+)/(feed|rdf|rss|rss2|atom)/?$\";s:81:\"index.php?year=$matches[1]&monthnum=$matches[2]&name=$matches[3]&feed=$matches[4]\";s:52:\"([0-9]{4})/([0-9]{1,2})/([^/]+)/page/?([0-9]{1,})/?$\";s:82:\"index.php?year=$matches[1]&monthnum=$matches[2]&name=$matches[3]&paged=$matches[4]\";s:59:\"([0-9]{4})/([0-9]{1,2})/([^/]+)/comment-page-([0-9]{1,})/?$\";s:82:\"index.php?year=$matches[1]&monthnum=$matches[2]&name=$matches[3]&cpage=$matches[4]\";s:48:\"([0-9]{4})/([0-9]{1,2})/([^/]+)(?:/([0-9]+))?/?$\";s:81:\"index.php?year=$matches[1]&monthnum=$matches[2]&name=$matches[3]&page=$matches[4]\";s:36:\"[0-9]{4}/[0-9]{1,2}/[^/]+/([^/]+)/?$\";s:32:\"index.php?attachment=$matches[1]\";s:46:\"[0-9]{4}/[0-9]{1,2}/[^/]+/([^/]+)/trackback/?$\";s:37:\"index.php?attachment=$matches[1]&tb=1\";s:66:\"[0-9]{4}/[0-9]{1,2}/[^/]+/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$\";s:49:\"index.php?attachment=$matches[1]&feed=$matches[2]\";s:61:\"[0-9]{4}/[0-9]{1,2}/[^/]+/([^/]+)/(feed|rdf|rss|rss2|atom)/?$\";s:49:\"index.php?attachment=$matches[1]&feed=$matches[2]\";s:61:\"[0-9]{4}/[0-9]{1,2}/[^/]+/([^/]+)/comment-page-([0-9]{1,})/?$\";s:50:\"index.php?attachment=$matches[1]&cpage=$matches[2]\";s:42:\"[0-9]{4}/[0-9]{1,2}/[^/]+/([^/]+)/embed/?$\";s:43:\"index.php?attachment=$matches[1]&embed=true\";s:51:\"([0-9]{4})/([0-9]{1,2})/comment-page-([0-9]{1,})/?$\";s:65:\"index.php?year=$matches[1]&monthnum=$matches[2]&cpage=$matches[3]\";s:38:\"([0-9]{4})/comment-page-([0-9]{1,})/?$\";s:44:\"index.php?year=$matches[1]&cpage=$matches[2]\";s:27:\".?.+?/attachment/([^/]+)/?$\";s:32:\"index.php?attachment=$matches[1]\";s:37:\".?.+?/attachment/([^/]+)/trackback/?$\";s:37:\"index.php?attachment=$matches[1]&tb=1\";s:57:\".?.+?/attachment/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$\";s:49:\"index.php?attachment=$matches[1]&feed=$matches[2]\";s:52:\".?.+?/attachment/([^/]+)/(feed|rdf|rss|rss2|atom)/?$\";s:49:\"index.php?attachment=$matches[1]&feed=$matches[2]\";s:52:\".?.+?/attachment/([^/]+)/comment-page-([0-9]{1,})/?$\";s:50:\"index.php?attachment=$matches[1]&cpage=$matches[2]\";s:33:\".?.+?/attachment/([^/]+)/embed/?$\";s:43:\"index.php?attachment=$matches[1]&embed=true\";s:16:\"(.?.+?)/embed/?$\";s:41:\"index.php?pagename=$matches[1]&embed=true\";s:20:\"(.?.+?)/trackback/?$\";s:35:\"index.php?pagename=$matches[1]&tb=1\";s:40:\"(.?.+?)/feed/(feed|rdf|rss|rss2|atom)/?$\";s:47:\"index.php?pagename=$matches[1]&feed=$matches[2]\";s:35:\"(.?.+?)/(feed|rdf|rss|rss2|atom)/?$\";s:47:\"index.php?pagename=$matches[1]&feed=$matches[2]\";s:28:\"(.?.+?)/page/?([0-9]{1,})/?$\";s:48:\"index.php?pagename=$matches[1]&paged=$matches[2]\";s:35:\"(.?.+?)/comment-page-([0-9]{1,})/?$\";s:48:\"index.php?pagename=$matches[1]&cpage=$matches[2]\";s:24:\"(.?.+?)(?:/([0-9]+))?/?$\";s:47:\"index.php?pagename=$matches[1]&page=$matches[2]\";}','on'),(31,'hack_file','0','on'),(32,'blog_charset','UTF-8','on'),(33,'moderation_keys','','off'),(34,'active_plugins','a:1:{i:0;s:45:\"secure-custom-fields/secure-custom-fields.php\";}','on'),(35,'category_base','thema','on'),(36,'ping_sites','https://rpc.pingomatic.com/','on'),(37,'comment_max_links','2','on'),(38,'gmt_offset','0','on'),(39,'default_email_category','1','on'),(40,'recently_edited','','off'),(41,'template','twentytwentyone','on'),(42,'stylesheet','twentytwentyone','on'),(43,'comment_registration','0','on'),(44,'html_type','text/html','on'),(45,'use_trackback','0','on'),(46,'default_role','subscriber','on'),(47,'db_version','61833','on'),(48,'uploads_use_yearmonth_folders','1','on'),(49,'upload_path','','on'),(50,'blog_public','1','on'),(51,'default_link_category','2','on'),(52,'show_on_front','posts','on'),(53,'tag_base','','on'),(54,'show_avatars','1','on'),(55,'avatar_rating','G','on'),(56,'upload_url_path','','on'),(57,'thumbnail_size_w','150','on'),(58,'thumbnail_size_h','150','on'),(59,'thumbnail_crop','1','on'),(60,'medium_size_w','300','on'),(61,'medium_size_h','300','on'),(62,'avatar_default','mystery','on'),(63,'large_size_w','1024','on'),(64,'large_size_h','1024','on'),(65,'image_default_link_type','none','on'),(66,'image_default_size','','on'),(67,'image_default_align','','on'),(68,'close_comments_for_old_posts','0','on'),(69,'close_comments_days_old','14','on'),(70,'thread_comments','1','on'),(71,'thread_comments_depth','5','on'),(72,'page_comments','0','on'),(73,'comments_per_page','50','on'),(74,'default_comments_page','newest','on'),(75,'comment_order','asc','on'),(76,'sticky_posts','a:0:{}','on'),(77,'widget_categories','a:0:{}','on'),(78,'widget_text','a:0:{}','on'),(79,'widget_rss','a:0:{}','on'),(80,'uninstall_plugins','a:1:{s:45:\"secure-custom-fields/secure-custom-fields.php\";s:20:\"scf_plugin_uninstall\";}','off'),(81,'timezone_string','Europe/Berlin','on'),(82,'page_for_posts','0','on'),(83,'page_on_front','0','on'),(84,'default_post_format','0','on'),(85,'link_manager_enabled','0','on'),(86,'finished_splitting_shared_terms','1','on'),(87,'site_icon','0','on'),(88,'medium_large_size_w','768','on'),(89,'medium_large_size_h','0','on'),(90,'wp_page_for_privacy_policy','0','on'),(91,'show_comments_cookies_opt_in','1','on'),(92,'admin_email_lifespan','1809680730','on'),(93,'disallowed_keys','','off'),(94,'comment_previously_approved','1','on'),(95,'auto_plugin_theme_update_emails','a:0:{}','off'),(96,'auto_update_core_dev','enabled','on'),(97,'auto_update_core_minor','enabled','on'),(98,'auto_update_core_major','enabled','on'),(99,'wp_force_deactivated_plugins','a:0:{}','on'),(100,'wp_attachment_pages_enabled','0','on'),(101,'wp_notes_notify','1','on'),(102,'initial_db_version','61833','on'),(103,'wp_user_roles','a:5:{s:13:\"administrator\";a:2:{s:4:\"name\";s:13:\"Administrator\";s:12:\"capabilities\";a:61:{s:13:\"switch_themes\";b:1;s:11:\"edit_themes\";b:1;s:16:\"activate_plugins\";b:1;s:12:\"edit_plugins\";b:1;s:10:\"edit_users\";b:1;s:10:\"edit_files\";b:1;s:14:\"manage_options\";b:1;s:17:\"moderate_comments\";b:1;s:17:\"manage_categories\";b:1;s:12:\"manage_links\";b:1;s:12:\"upload_files\";b:1;s:6:\"import\";b:1;s:15:\"unfiltered_html\";b:1;s:10:\"edit_posts\";b:1;s:17:\"edit_others_posts\";b:1;s:20:\"edit_published_posts\";b:1;s:13:\"publish_posts\";b:1;s:10:\"edit_pages\";b:1;s:4:\"read\";b:1;s:8:\"level_10\";b:1;s:7:\"level_9\";b:1;s:7:\"level_8\";b:1;s:7:\"level_7\";b:1;s:7:\"level_6\";b:1;s:7:\"level_5\";b:1;s:7:\"level_4\";b:1;s:7:\"level_3\";b:1;s:7:\"level_2\";b:1;s:7:\"level_1\";b:1;s:7:\"level_0\";b:1;s:17:\"edit_others_pages\";b:1;s:20:\"edit_published_pages\";b:1;s:13:\"publish_pages\";b:1;s:12:\"delete_pages\";b:1;s:19:\"delete_others_pages\";b:1;s:22:\"delete_published_pages\";b:1;s:12:\"delete_posts\";b:1;s:19:\"delete_others_posts\";b:1;s:22:\"delete_published_posts\";b:1;s:20:\"delete_private_posts\";b:1;s:18:\"edit_private_posts\";b:1;s:18:\"read_private_posts\";b:1;s:20:\"delete_private_pages\";b:1;s:18:\"edit_private_pages\";b:1;s:18:\"read_private_pages\";b:1;s:12:\"delete_users\";b:1;s:12:\"create_users\";b:1;s:17:\"unfiltered_upload\";b:1;s:14:\"edit_dashboard\";b:1;s:14:\"update_plugins\";b:1;s:14:\"delete_plugins\";b:1;s:15:\"install_plugins\";b:1;s:13:\"update_themes\";b:1;s:14:\"install_themes\";b:1;s:11:\"update_core\";b:1;s:10:\"list_users\";b:1;s:12:\"remove_users\";b:1;s:13:\"promote_users\";b:1;s:18:\"edit_theme_options\";b:1;s:13:\"delete_themes\";b:1;s:6:\"export\";b:1;}}s:6:\"editor\";a:2:{s:4:\"name\";s:6:\"Editor\";s:12:\"capabilities\";a:34:{s:17:\"moderate_comments\";b:1;s:17:\"manage_categories\";b:1;s:12:\"manage_links\";b:1;s:12:\"upload_files\";b:1;s:15:\"unfiltered_html\";b:1;s:10:\"edit_posts\";b:1;s:17:\"edit_others_posts\";b:1;s:20:\"edit_published_posts\";b:1;s:13:\"publish_posts\";b:1;s:10:\"edit_pages\";b:1;s:4:\"read\";b:1;s:7:\"level_7\";b:1;s:7:\"level_6\";b:1;s:7:\"level_5\";b:1;s:7:\"level_4\";b:1;s:7:\"level_3\";b:1;s:7:\"level_2\";b:1;s:7:\"level_1\";b:1;s:7:\"level_0\";b:1;s:17:\"edit_others_pages\";b:1;s:20:\"edit_published_pages\";b:1;s:13:\"publish_pages\";b:1;s:12:\"delete_pages\";b:1;s:19:\"delete_others_pages\";b:1;s:22:\"delete_published_pages\";b:1;s:12:\"delete_posts\";b:1;s:19:\"delete_others_posts\";b:1;s:22:\"delete_published_posts\";b:1;s:20:\"delete_private_posts\";b:1;s:18:\"edit_private_posts\";b:1;s:18:\"read_private_posts\";b:1;s:20:\"delete_private_pages\";b:1;s:18:\"edit_private_pages\";b:1;s:18:\"read_private_pages\";b:1;}}s:6:\"author\";a:2:{s:4:\"name\";s:6:\"Author\";s:12:\"capabilities\";a:10:{s:12:\"upload_files\";b:1;s:10:\"edit_posts\";b:1;s:20:\"edit_published_posts\";b:1;s:13:\"publish_posts\";b:1;s:4:\"read\";b:1;s:7:\"level_2\";b:1;s:7:\"level_1\";b:1;s:7:\"level_0\";b:1;s:12:\"delete_posts\";b:1;s:22:\"delete_published_posts\";b:1;}}s:11:\"contributor\";a:2:{s:4:\"name\";s:11:\"Contributor\";s:12:\"capabilities\";a:5:{s:10:\"edit_posts\";b:1;s:4:\"read\";b:1;s:7:\"level_1\";b:1;s:7:\"level_0\";b:1;s:12:\"delete_posts\";b:1;}}s:10:\"subscriber\";a:2:{s:4:\"name\";s:10:\"Subscriber\";s:12:\"capabilities\";a:2:{s:4:\"read\";b:1;s:7:\"level_0\";b:1;}}}','on'),(104,'fresh_site','0','off'),(105,'user_count','5','off'),(106,'widget_block','a:6:{i:2;a:1:{s:7:\"content\";s:19:\"<!-- wp:search /-->\";}i:3;a:1:{s:7:\"content\";s:154:\"<!-- wp:group --><div class=\"wp-block-group\"><!-- wp:heading --><h2>Recent Posts</h2><!-- /wp:heading --><!-- wp:latest-posts /--></div><!-- /wp:group -->\";}i:4;a:1:{s:7:\"content\";s:227:\"<!-- wp:group --><div class=\"wp-block-group\"><!-- wp:heading --><h2>Recent Comments</h2><!-- /wp:heading --><!-- wp:latest-comments {\"displayAvatar\":false,\"displayDate\":false,\"displayExcerpt\":false} /--></div><!-- /wp:group -->\";}i:5;a:1:{s:7:\"content\";s:146:\"<!-- wp:group --><div class=\"wp-block-group\"><!-- wp:heading --><h2>Archives</h2><!-- /wp:heading --><!-- wp:archives /--></div><!-- /wp:group -->\";}i:6;a:1:{s:7:\"content\";s:150:\"<!-- wp:group --><div class=\"wp-block-group\"><!-- wp:heading --><h2>Categories</h2><!-- /wp:heading --><!-- wp:categories /--></div><!-- /wp:group -->\";}s:12:\"_multiwidget\";i:1;}','auto'),(107,'sidebars_widgets','a:3:{s:19:\"wp_inactive_widgets\";a:0:{}s:9:\"sidebar-1\";a:5:{i:0;s:7:\"block-2\";i:1;s:7:\"block-3\";i:2;s:7:\"block-4\";i:3;s:7:\"block-5\";i:4;s:7:\"block-6\";}s:13:\"array_version\";i:3;}','auto'),(108,'widget_pages','a:1:{s:12:\"_multiwidget\";i:1;}','auto'),(109,'widget_calendar','a:1:{s:12:\"_multiwidget\";i:1;}','auto'),(110,'widget_archives','a:1:{s:12:\"_multiwidget\";i:1;}','auto'),(111,'widget_media_audio','a:1:{s:12:\"_multiwidget\";i:1;}','auto'),(112,'widget_media_image','a:1:{s:12:\"_multiwidget\";i:1;}','auto'),(113,'widget_media_gallery','a:1:{s:12:\"_multiwidget\";i:1;}','auto'),(114,'widget_media_video','a:1:{s:12:\"_multiwidget\";i:1;}','auto'),(115,'widget_meta','a:1:{s:12:\"_multiwidget\";i:1;}','auto'),(116,'widget_search','a:1:{s:12:\"_multiwidget\";i:1;}','auto'),(117,'widget_recent-posts','a:1:{s:12:\"_multiwidget\";i:1;}','auto'),(118,'widget_recent-comments','a:1:{s:12:\"_multiwidget\";i:1;}','auto'),(119,'widget_tag_cloud','a:1:{s:12:\"_multiwidget\";i:1;}','auto'),(120,'widget_nav_menu','a:1:{s:12:\"_multiwidget\";i:1;}','auto'),(121,'widget_custom_html','a:1:{s:12:\"_multiwidget\";i:1;}','auto'),(126,'_site_transient_timeout_theme_roots','1794130533','off'),(127,'_site_transient_theme_roots','a:4:{s:16:\"twentytwentyfive\";s:7:\"/themes\";s:16:\"twentytwentyfour\";s:7:\"/themes\";s:15:\"twentytwentyone\";s:7:\"/themes\";s:17:\"twentytwentythree\";s:7:\"/themes\";}','off'),(128,'_site_transient_update_core','O:8:\"stdClass\":4:{s:7:\"updates\";a:1:{i:0;O:8:\"stdClass\":10:{s:8:\"response\";s:6:\"latest\";s:8:\"download\";s:59:\"https://downloads.wordpress.org/release/wordpress-7.1.4.zip\";s:6:\"locale\";s:5:\"en_US\";s:8:\"packages\";O:8:\"stdClass\":5:{s:4:\"full\";s:59:\"https://downloads.wordpress.org/release/wordpress-7.1.4.zip\";s:10:\"no_content\";s:70:\"https://downloads.wordpress.org/release/wordpress-7.1.4-no-content.zip\";s:11:\"new_bundled\";s:71:\"https://downloads.wordpress.org/release/wordpress-7.1.4-new-bundled.zip\";s:7:\"partial\";s:0:\"\";s:8:\"rollback\";s:0:\"\";}s:7:\"current\";s:5:\"7.1.4\";s:7:\"version\";s:5:\"7.1.4\";s:11:\"php_version\";s:3:\"7.4\";s:13:\"mysql_version\";s:5:\"5.5.5\";s:11:\"new_bundled\";s:3:\"6.7\";s:15:\"partial_version\";s:0:\"\";}}s:12:\"last_checked\";i:1794128733;s:15:\"version_checked\";s:5:\"7.1.4\";s:12:\"translations\";a:0:{}}','off'),(130,'theme_mods_twentytwentyfive','a:1:{s:16:\"sidebars_widgets\";a:2:{s:4:\"time\";i:1794128734;s:4:\"data\";a:3:{s:19:\"wp_inactive_widgets\";a:0:{}s:9:\"sidebar-1\";a:3:{i:0;s:7:\"block-2\";i:1;s:7:\"block-3\";i:2;s:7:\"block-4\";}s:9:\"sidebar-2\";a:2:{i:0;s:7:\"block-5\";i:1;s:7:\"block-6\";}}}}','off'),(131,'current_theme','Twenty Twenty-One','auto'),(132,'theme_switched','','auto'),(134,'_site_transient_timeout_wp_theme_files_patterns-308e0c7ffedf924ff371a259449c44be','1794130535','off'),(135,'_site_transient_wp_theme_files_patterns-308e0c7ffedf924ff371a259449c44be','a:2:{s:7:\"version\";s:3:\"2.9\";s:8:\"patterns\";a:0:{}}','off'),(136,'theme_mods_twentytwentyone','a:1:{s:18:\"nav_menu_locations\";a:1:{s:7:\"primary\";i:5;}}','auto'),(138,'_site_transient_update_themes','O:8:\"stdClass\":5:{s:12:\"last_checked\";i:1794128737;s:7:\"checked\";a:4:{s:16:\"twentytwentyfive\";s:3:\"1.5\";s:16:\"twentytwentyfour\";s:3:\"1.6\";s:15:\"twentytwentyone\";s:3:\"2.9\";s:17:\"twentytwentythree\";s:3:\"1.7\";}s:8:\"response\";a:0:{}s:9:\"no_update\";a:4:{s:16:\"twentytwentyfive\";a:6:{s:5:\"theme\";s:16:\"twentytwentyfive\";s:11:\"new_version\";s:3:\"1.5\";s:3:\"url\";s:46:\"https://wordpress.org/themes/twentytwentyfive/\";s:7:\"package\";s:62:\"https://downloads.wordpress.org/theme/twentytwentyfive.1.5.zip\";s:8:\"requires\";s:3:\"6.7\";s:12:\"requires_php\";s:3:\"7.2\";}s:16:\"twentytwentyfour\";a:6:{s:5:\"theme\";s:16:\"twentytwentyfour\";s:11:\"new_version\";s:3:\"1.6\";s:3:\"url\";s:46:\"https://wordpress.org/themes/twentytwentyfour/\";s:7:\"package\";s:62:\"https://downloads.wordpress.org/theme/twentytwentyfour.1.6.zip\";s:8:\"requires\";s:3:\"6.4\";s:12:\"requires_php\";s:3:\"7.0\";}s:15:\"twentytwentyone\";a:6:{s:5:\"theme\";s:15:\"twentytwentyone\";s:11:\"new_version\";s:3:\"2.9\";s:3:\"url\";s:45:\"https://wordpress.org/themes/twentytwentyone/\";s:7:\"package\";s:61:\"https://downloads.wordpress.org/theme/twentytwentyone.2.9.zip\";s:8:\"requires\";s:3:\"5.3\";s:12:\"requires_php\";s:3:\"5.6\";}s:17:\"twentytwentythree\";a:6:{s:5:\"theme\";s:17:\"twentytwentythree\";s:11:\"new_version\";s:3:\"1.7\";s:3:\"url\";s:47:\"https://wordpress.org/themes/twentytwentythree/\";s:7:\"package\";s:63:\"https://downloads.wordpress.org/theme/twentytwentythree.1.7.zip\";s:8:\"requires\";s:3:\"6.1\";s:12:\"requires_php\";s:3:\"5.6\";}}s:12:\"translations\";a:0:{}}','off'),(139,'acf_first_activated_version','6.9.8','on'),(140,'acf_site_health','{\"event_first_activated_pro\":1794128738,\"last_updated\":1794128739,\"event_first_created_field_group\":1794128739}','off'),(141,'wp_calendar_block_has_published_posts','1','auto'),(142,'wpseo_titles','a:1:{s:9:\"separator\";s:7:\"sc-pipe\";}','auto'),(143,'rank-math-options-titles','a:1:{s:15:\"title_separator\";s:3:\"–\";}','auto'),(145,'category_children','a:1:{i:2;a:1:{i:0;i:3;}}','auto');
/*!40000 ALTER TABLE `wp_options` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_postmeta`
--

DROP TABLE IF EXISTS `wp_postmeta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_postmeta` (
  `meta_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `post_id` bigint unsigned NOT NULL DEFAULT '0',
  `meta_key` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `meta_value` longtext COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`meta_id`),
  KEY `post_id` (`post_id`),
  KEY `meta_key` (`meta_key`(191))
) ENGINE=InnoDB AUTO_INCREMENT=106 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_postmeta`
--

LOCK TABLES `wp_postmeta` WRITE;
/*!40000 ALTER TABLE `wp_postmeta` DISABLE KEYS */;
INSERT INTO `wp_postmeta` VALUES (3,4,'rank_math_title','%title% %sep% %sitename%'),(4,4,'rank_math_description','Who we are and what we do.'),(5,4,'_wp_page_template','template-wide.php'),(6,6,'_pingme','1'),(7,6,'_encloseme','1'),(8,7,'_wp_attached_file','2026/01/hero.png'),(9,7,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:1200;s:6:\"height\";i:800;s:4:\"file\";s:16:\"2026/01/hero.png\";s:8:\"filesize\";i:4610;s:5:\"sizes\";a:4:{s:6:\"medium\";a:5:{s:4:\"file\";s:16:\"hero-300x200.png\";s:5:\"width\";i:300;s:6:\"height\";i:200;s:9:\"mime-type\";s:9:\"image/png\";s:8:\"filesize\";i:764;}s:5:\"large\";a:5:{s:4:\"file\";s:17:\"hero-1024x683.png\";s:5:\"width\";i:1024;s:6:\"height\";i:683;s:9:\"mime-type\";s:9:\"image/png\";s:8:\"filesize\";i:4382;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:16:\"hero-150x150.png\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:9:\"image/png\";s:8:\"filesize\";i:490;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:16:\"hero-768x512.png\";s:5:\"width\";i:768;s:6:\"height\";i:512;s:9:\"mime-type\";s:9:\"image/png\";s:8:\"filesize\";i:2736;}}s:10:\"image_meta\";a:13:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}s:3:\"alt\";s:0:\"\";}}'),(10,8,'_wp_attached_file','2026/01/second.png'),(11,8,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:800;s:6:\"height\";i:600;s:4:\"file\";s:18:\"2026/01/second.png\";s:8:\"filesize\";i:2812;s:5:\"sizes\";a:3:{s:6:\"medium\";a:5:{s:4:\"file\";s:18:\"second-300x225.png\";s:5:\"width\";i:300;s:6:\"height\";i:225;s:9:\"mime-type\";s:9:\"image/png\";s:8:\"filesize\";i:845;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:18:\"second-150x150.png\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:9:\"image/png\";s:8:\"filesize\";i:489;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:18:\"second-768x576.png\";s:5:\"width\";i:768;s:6:\"height\";i:576;s:9:\"mime-type\";s:9:\"image/png\";s:8:\"filesize\";i:3064;}}s:10:\"image_meta\";a:13:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}s:3:\"alt\";s:0:\"\";}}'),(12,7,'_wp_attachment_image_alt','A red rectangle'),(13,6,'_thumbnail_id','7'),(14,10,'_pingme','1'),(15,10,'_encloseme','1'),(16,10,'_yoast_wpseo_title','%%title%% %%sep%% %%sitename%%'),(17,10,'_yoast_wpseo_metadesc','%%excerpt%%'),(18,10,'_yoast_wpseo_canonical','https://old.example.com/2026/02/block-story/'),(19,10,'_yoast_wpseo_meta-robots-nofollow','1'),(20,10,'_yoast_wpseo_opengraph-image-id','7'),(21,10,'_yoast_wpseo_primary_category','3'),(22,32,'subtitle','An evening outdoors'),(23,32,'_subtitle','field_subtitle'),(24,32,'sold_out','1'),(25,32,'_sold_out','field_sold_out'),(26,32,'date','20260715'),(27,32,'_date','field_date'),(28,32,'doors','2026-07-15 19:00:00'),(29,32,'_doors','field_doors'),(30,32,'price','25.5'),(31,32,'_price','field_price'),(32,32,'poster','7'),(33,32,'_poster','field_poster'),(34,32,'photos','a:2:{i:0;s:1:\"8\";i:1;s:1:\"7\";}'),(35,32,'_photos','field_photos'),(36,32,'related','a:1:{i:0;s:2:\"10\";}'),(37,32,'_related','field_related'),(38,32,'tickets','a:3:{s:5:\"title\";s:11:\"Buy tickets\";s:3:\"url\";s:30:\"https://old.example.com/about/\";s:6:\"target\";s:6:\"_blank\";}'),(39,32,'_tickets','field_link'),(40,32,'lineup_0_artist','The Openers'),(41,32,'_lineup_0_artist','field_lineup_artist'),(42,32,'lineup_0_headliner','0'),(43,32,'_lineup_0_headliner','field_lineup_headliner'),(44,32,'lineup_1_artist','The Headliners'),(45,32,'_lineup_1_artist','field_lineup_artist'),(46,32,'lineup_1_headliner','1'),(47,32,'_lineup_1_headliner','field_lineup_headliner'),(48,32,'lineup','2'),(49,32,'_lineup','field_lineup'),(50,32,'sections_0_body','First paragraph.\n\nSecond paragraph.'),(51,32,'_sections_0_body','field_sections_body'),(52,32,'sections_1_quote','Unforgettable'),(53,32,'_sections_1_quote','field_sections_quote'),(54,32,'sections_1_by','A visitor'),(55,32,'_sections_1_by','field_sections_by'),(56,32,'_sections_layout_meta','a:2:{s:8:\"disabled\";a:0:{}s:7:\"renamed\";a:0:{}}'),(57,32,'sections','a:2:{i:0;s:4:\"text\";i:1;s:5:\"quote\";}'),(58,32,'_sections','field_sections'),(59,32,'venue_name','Open Air Stage'),(60,32,'_venue_name','field_venue_name'),(61,32,'venue_capacity','800'),(62,32,'_venue_capacity','field_venue_capacity'),(63,32,'venue',''),(64,32,'_venue','field_venue'),(65,33,'_elementor_data','[{\"id\":\"a1b2\",\"elType\":\"section\"}]'),(66,35,'_menu_item_type','custom'),(67,35,'_menu_item_menu_item_parent','0'),(68,35,'_menu_item_object_id','35'),(69,35,'_menu_item_object','custom'),(70,35,'_menu_item_target',''),(71,35,'_menu_item_classes','a:1:{i:0;s:0:\"\";}'),(72,35,'_menu_item_xfn',''),(73,35,'_menu_item_url','https://old.example.com/'),(74,36,'_menu_item_type','post_type'),(75,36,'_menu_item_menu_item_parent','0'),(76,36,'_menu_item_object_id','4'),(77,36,'_menu_item_object','page'),(78,36,'_menu_item_target',''),(79,36,'_menu_item_classes','a:1:{i:0;s:0:\"\";}'),(80,36,'_menu_item_xfn',''),(81,36,'_menu_item_url',''),(82,37,'_menu_item_type','post_type'),(83,37,'_menu_item_menu_item_parent','36'),(84,37,'_menu_item_object_id','5'),(85,37,'_menu_item_object','page'),(86,37,'_menu_item_target',''),(87,37,'_menu_item_classes','a:1:{i:0;s:0:\"\";}'),(88,37,'_menu_item_xfn',''),(89,37,'_menu_item_url',''),(90,38,'_menu_item_type','taxonomy'),(91,38,'_menu_item_menu_item_parent','0'),(92,38,'_menu_item_object_id','2'),(93,38,'_menu_item_object','category'),(94,38,'_menu_item_target',''),(95,38,'_menu_item_classes','a:1:{i:0;s:0:\"\";}'),(96,38,'_menu_item_xfn',''),(97,38,'_menu_item_url',''),(98,39,'_menu_item_type','custom'),(99,39,'_menu_item_menu_item_parent','0'),(100,39,'_menu_item_object_id','39'),(101,39,'_menu_item_object','custom'),(102,39,'_menu_item_target','_blank'),(103,39,'_menu_item_classes','a:1:{i:0;s:8:\"external\";}'),(104,39,'_menu_item_xfn',''),(105,39,'_menu_item_url','https://example.net/');
/*!40000 ALTER TABLE `wp_postmeta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_posts`
--

DROP TABLE IF EXISTS `wp_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_posts` (
  `ID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `post_author` bigint unsigned NOT NULL DEFAULT '0',
  `post_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_date_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_content` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `post_title` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `post_excerpt` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `post_status` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'publish',
  `comment_status` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'open',
  `ping_status` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'open',
  `post_password` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `post_name` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `to_ping` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `pinged` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `post_modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_modified_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_content_filtered` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `post_parent` bigint unsigned NOT NULL DEFAULT '0',
  `guid` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `menu_order` int NOT NULL DEFAULT '0',
  `post_type` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'post',
  `post_mime_type` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `comment_count` bigint NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`),
  KEY `post_name` (`post_name`(191)),
  KEY `type_status_date` (`post_type`,`post_status`,`post_date`,`ID`),
  KEY `post_parent` (`post_parent`),
  KEY `post_author` (`post_author`),
  KEY `type_status_author` (`post_type`,`post_status`,`post_author`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_posts`
--

LOCK TABLES `wp_posts` WRITE;
/*!40000 ALTER TABLE `wp_posts` DISABLE KEYS */;
INSERT INTO `wp_posts` VALUES (4,1,'2026-01-10 10:00:00','2026-01-10 09:00:00','We are a small team.\n\nWe build things that last.','About us','','publish','closed','closed','','about','','','2026-01-10 10:00:00','2026-01-10 09:00:00','',0,'https://old.example.com/about/',0,'page','',0),(5,1,'2026-01-10 10:00:00','2026-01-10 09:00:00','<!-- wp:paragraph --><p>Meet the team.</p><!-- /wp:paragraph -->','Team','','publish','closed','closed','','team','','','2026-01-10 10:00:00','2026-01-10 09:00:00','',4,'https://old.example.com/about/team/',1,'page','',0),(6,3,'2026-01-10 10:00:00','2026-01-10 09:00:00','Welcome to the classic story.\nIt has a line break.\n\nIt links to the <a href=\"https://old.example.com/2026/02/block-story/\">block story</a>.\n\n[caption id=\"attachment_7\" align=\"alignleft\" width=\"300\"]<img src=\"https://old.example.com/wp-content/uploads/2026/01/hero-300x200.png\" alt=\"A red rectangle\" width=\"300\" height=\"200\" class=\"size-medium wp-image-7\" /> A captioned image[/caption]\n\n[gallery ids=\"7,8\" columns=\"2\" size=\"medium\"]\n\n[embed]https://vimeo.com/123456[/embed]\n\n[contact-form-7 id=\"42\" title=\"Contact\"]\n\n<pre>Preformatted\n\n  text</pre>','Classic Story','A post from the classic editor.','publish','open','open','','classic-story','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',0,'https://old.example.com/2026/01/classic-story/',0,'post','',2),(7,0,'2026-11-08 10:05:39','2026-11-08 09:05:39','','Hero','','inherit','open','closed','','hero','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',6,'https://old.example.com/wp-content/uploads/2026/01/hero.png',0,'attachment','image/png',0),(8,0,'2026-11-08 10:05:39','2026-11-08 09:05:39','','Second','','inherit','open','closed','','second','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',6,'https://old.example.com/wp-content/uploads/2026/01/second.png',0,'attachment','image/png',0),(9,1,'2026-01-10 10:00:00','2026-01-10 09:00:00','<!-- wp:paragraph {\"className\":\"cta\"} --><p class=\"cta\">Get in touch!</p><!-- /wp:paragraph -->','Call to action','','publish','closed','closed','','call-to-action','','','2026-01-10 10:00:00','2026-01-10 09:00:00','',0,'https://old.example.com/2026/01/call-to-action/',0,'wp_block','',0),(10,2,'2026-02-14 09:30:00','2026-02-14 08:30:00','<!-- wp:heading {\"level\":2} --><h2 class=\"wp-block-heading\">A block post</h2><!-- /wp:heading -->\n\n<!-- wp:paragraph --><p>Read more <a href=\"https://old.example.com/about/\">about us</a>.</p><!-- /wp:paragraph -->\n\n<!-- wp:image {\"id\":7,\"sizeSlug\":\"medium\"} --><figure class=\"wp-block-image size-medium\"><img src=\"https://old.example.com/wp-content/uploads/2026/01/hero-300x200.png\" alt=\"\" class=\"wp-image-7\"/></figure><!-- /wp:image -->\n\n<!-- wp:group {\"layout\":{\"type\":\"constrained\"}} --><div class=\"wp-block-group\"><!-- wp:columns --><div class=\"wp-block-columns\"><!-- wp:column --><div class=\"wp-block-column\"><!-- wp:list --><ul class=\"wp-block-list\"><!-- wp:list-item --><li>One</li><!-- /wp:list-item --><!-- wp:list-item --><li>Two</li><!-- /wp:list-item --></ul><!-- /wp:list --></div><!-- /wp:column --><!-- wp:column --><div class=\"wp-block-column\"><!-- wp:quote --><blockquote class=\"wp-block-quote\"><!-- wp:paragraph --><p>Less is more.</p><!-- /wp:paragraph --></blockquote><!-- /wp:quote --></div><!-- /wp:column --></div><!-- /wp:columns --></div><!-- /wp:group -->\n\n<!-- wp:embed {\"url\":\"https://www.youtube.com/watch?v=abc123\",\"type\":\"video\",\"providerNameSlug\":\"youtube\"} --><figure class=\"wp-block-embed is-type-video\"><div class=\"wp-block-embed__wrapper\">https://www.youtube.com/watch?v=abc123</div></figure><!-- /wp:embed -->\n\n<!-- wp:code --><pre class=\"wp-block-code\"><code>[gallery] stays literal</code></pre><!-- /wp:code -->\n\n<!-- wp:block {\"ref\":9} /-->\n\n<!-- wp:acme/hero {\"title\":\"Custom block\"} --><div class=\"acme-hero\">Custom block</div><!-- /wp:acme/hero -->','Block Story','','publish','open','open','','block-story','','','2026-02-14 09:30:00','2026-02-14 08:30:00','',0,'https://old.example.com/2026/02/block-story/',0,'post','',0),(11,0,'2026-11-08 10:05:39','2026-11-08 09:05:39','Welcome to the classic story.\nIt has a line break.\n\nIt links to the <a href=\"https://old.example.com/2026/02/block-story/\">block story</a>.\n\n[caption id=\"attachment_7\" align=\"alignleft\" width=\"300\"]<img src=\"https://old.example.com/wp-content/uploads/2026/01/hero-300x200.png\" alt=\"A red rectangle\" width=\"300\" height=\"200\" class=\"size-medium wp-image-7\" /> A captioned image[/caption]\n\n[gallery ids=\"7,8\" columns=\"2\" size=\"medium\"]\n\n[embed]https://vimeo.com/123456[/embed]\n\n[contact-form-7 id=\"42\" title=\"Contact\"]\n\n<pre>Preformatted\n\n  text</pre>','Classic Story','A post from the classic editor.','inherit','closed','closed','','6-revision-v1','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',6,'https://old.example.com/?p=11',0,'revision','',0),(12,0,'2026-11-08 10:05:39','2026-11-08 09:05:39','a:11:{s:8:\"location\";a:1:{i:0;a:1:{i:0;a:3:{s:5:\"param\";s:9:\"post_type\";s:8:\"operator\";s:2:\"==\";s:5:\"value\";s:5:\"event\";}}}s:8:\"position\";s:6:\"normal\";s:5:\"style\";s:7:\"default\";s:15:\"label_placement\";s:3:\"top\";s:21:\"instruction_placement\";s:5:\"label\";s:14:\"hide_on_screen\";a:0:{}s:11:\"description\";s:0:\"\";s:12:\"show_in_rest\";b:0;s:13:\"display_title\";s:0:\"\";s:15:\"allow_ai_access\";b:0;s:14:\"ai_description\";s:0:\"\";}','Event details','event-details','publish','closed','closed','','group_event','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',0,'https://old.example.com/?post_type=acf-field-group&#038;p=12',0,'acf-field-group','',0),(13,0,'2026-11-08 10:05:39','2026-11-08 09:05:39','a:11:{s:10:\"aria-label\";s:0:\"\";s:4:\"type\";s:4:\"text\";s:12:\"instructions\";s:0:\"\";s:8:\"required\";b:0;s:17:\"conditional_logic\";b:0;s:7:\"wrapper\";a:3:{s:5:\"width\";s:0:\"\";s:5:\"class\";s:0:\"\";s:2:\"id\";s:0:\"\";}s:13:\"default_value\";s:0:\"\";s:9:\"maxlength\";s:0:\"\";s:11:\"placeholder\";s:0:\"\";s:7:\"prepend\";s:0:\"\";s:6:\"append\";s:0:\"\";}','Subtitle','subtitle','publish','closed','closed','','field_subtitle','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',12,'https://old.example.com/?post_type=acf-field&p=13',0,'acf-field','',0),(14,0,'2026-11-08 10:05:39','2026-11-08 09:05:39','a:11:{s:10:\"aria-label\";s:0:\"\";s:4:\"type\";s:10:\"true_false\";s:12:\"instructions\";s:0:\"\";s:8:\"required\";b:0;s:17:\"conditional_logic\";b:0;s:7:\"wrapper\";a:3:{s:5:\"width\";s:0:\"\";s:5:\"class\";s:0:\"\";s:2:\"id\";s:0:\"\";}s:13:\"default_value\";i:0;s:7:\"message\";s:0:\"\";s:2:\"ui\";i:0;s:10:\"ui_on_text\";s:0:\"\";s:11:\"ui_off_text\";s:0:\"\";}','Sold out','sold_out','publish','closed','closed','','field_sold_out','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',12,'https://old.example.com/?post_type=acf-field&p=14',1,'acf-field','',0),(15,0,'2026-11-08 10:05:39','2026-11-08 09:05:39','a:10:{s:10:\"aria-label\";s:0:\"\";s:4:\"type\";s:11:\"date_picker\";s:12:\"instructions\";s:0:\"\";s:8:\"required\";b:0;s:17:\"conditional_logic\";b:0;s:7:\"wrapper\";a:3:{s:5:\"width\";s:0:\"\";s:5:\"class\";s:0:\"\";s:2:\"id\";s:0:\"\";}s:14:\"display_format\";s:5:\"d/m/Y\";s:13:\"return_format\";s:5:\"d/m/Y\";s:9:\"first_day\";i:1;s:23:\"default_to_current_date\";i:0;}','Date','date','publish','closed','closed','','field_date','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',12,'https://old.example.com/?post_type=acf-field&p=15',2,'acf-field','',0),(16,0,'2026-11-08 10:05:39','2026-11-08 09:05:39','a:10:{s:10:\"aria-label\";s:0:\"\";s:4:\"type\";s:16:\"date_time_picker\";s:12:\"instructions\";s:0:\"\";s:8:\"required\";b:0;s:17:\"conditional_logic\";b:0;s:7:\"wrapper\";a:3:{s:5:\"width\";s:0:\"\";s:5:\"class\";s:0:\"\";s:2:\"id\";s:0:\"\";}s:14:\"display_format\";s:11:\"d/m/Y g:i a\";s:13:\"return_format\";s:11:\"d/m/Y g:i a\";s:9:\"first_day\";i:1;s:23:\"default_to_current_date\";i:0;}','Doors','doors','publish','closed','closed','','field_doors','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',12,'https://old.example.com/?post_type=acf-field&p=16',3,'acf-field','',0),(17,0,'2026-11-08 10:05:39','2026-11-08 09:05:39','a:13:{s:10:\"aria-label\";s:0:\"\";s:4:\"type\";s:6:\"number\";s:12:\"instructions\";s:0:\"\";s:8:\"required\";b:0;s:17:\"conditional_logic\";b:0;s:7:\"wrapper\";a:3:{s:5:\"width\";s:0:\"\";s:5:\"class\";s:0:\"\";s:2:\"id\";s:0:\"\";}s:13:\"default_value\";s:0:\"\";s:3:\"min\";s:0:\"\";s:3:\"max\";s:0:\"\";s:4:\"step\";s:0:\"\";s:11:\"placeholder\";s:0:\"\";s:7:\"prepend\";s:0:\"\";s:6:\"append\";s:0:\"\";}','Price','price','publish','closed','closed','','field_price','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',12,'https://old.example.com/?post_type=acf-field&p=17',4,'acf-field','',0),(18,0,'2026-11-08 10:05:39','2026-11-08 09:05:39','a:16:{s:10:\"aria-label\";s:0:\"\";s:4:\"type\";s:5:\"image\";s:12:\"instructions\";s:0:\"\";s:8:\"required\";b:0;s:17:\"conditional_logic\";b:0;s:7:\"wrapper\";a:3:{s:5:\"width\";s:0:\"\";s:5:\"class\";s:0:\"\";s:2:\"id\";s:0:\"\";}s:13:\"return_format\";s:5:\"array\";s:12:\"preview_size\";s:6:\"medium\";s:7:\"library\";s:3:\"all\";s:9:\"min_width\";i:0;s:10:\"min_height\";i:0;s:8:\"min_size\";i:0;s:9:\"max_width\";i:0;s:10:\"max_height\";i:0;s:8:\"max_size\";i:0;s:10:\"mime_types\";s:0:\"\";}','Poster','poster','publish','closed','closed','','field_poster','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',12,'https://old.example.com/?post_type=acf-field&p=18',5,'acf-field','',0),(19,0,'2026-11-08 10:05:39','2026-11-08 09:05:39','a:19:{s:10:\"aria-label\";s:0:\"\";s:4:\"type\";s:7:\"gallery\";s:12:\"instructions\";s:0:\"\";s:8:\"required\";b:0;s:17:\"conditional_logic\";b:0;s:7:\"wrapper\";a:3:{s:5:\"width\";s:0:\"\";s:5:\"class\";s:0:\"\";s:2:\"id\";s:0:\"\";}s:13:\"return_format\";s:5:\"array\";s:12:\"preview_size\";s:6:\"medium\";s:6:\"insert\";s:6:\"append\";s:7:\"library\";s:3:\"all\";s:3:\"min\";i:0;s:3:\"max\";i:0;s:9:\"min_width\";i:0;s:10:\"min_height\";i:0;s:8:\"min_size\";i:0;s:9:\"max_width\";i:0;s:10:\"max_height\";i:0;s:8:\"max_size\";i:0;s:10:\"mime_types\";s:0:\"\";}','Photos','photos','publish','closed','closed','','field_photos','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',12,'https://old.example.com/?post_type=acf-field&p=19',6,'acf-field','',0),(20,0,'2026-11-08 10:05:39','2026-11-08 09:05:39','a:14:{s:10:\"aria-label\";s:0:\"\";s:4:\"type\";s:12:\"relationship\";s:12:\"instructions\";s:0:\"\";s:8:\"required\";b:0;s:17:\"conditional_logic\";b:0;s:7:\"wrapper\";a:3:{s:5:\"width\";s:0:\"\";s:5:\"class\";s:0:\"\";s:2:\"id\";s:0:\"\";}s:9:\"post_type\";a:0:{}s:8:\"taxonomy\";a:0:{}s:3:\"min\";i:0;s:3:\"max\";i:0;s:7:\"filters\";a:3:{i:0;s:6:\"search\";i:1;s:9:\"post_type\";i:2;s:8:\"taxonomy\";}s:8:\"elements\";a:0:{}s:13:\"return_format\";s:6:\"object\";s:20:\"bidirectional_target\";a:0:{}}','Related','related','publish','closed','closed','','field_related','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',12,'https://old.example.com/?post_type=acf-field&p=20',7,'acf-field','',0),(21,0,'2026-11-08 10:05:39','2026-11-08 09:05:39','a:7:{s:10:\"aria-label\";s:0:\"\";s:4:\"type\";s:4:\"link\";s:12:\"instructions\";s:0:\"\";s:8:\"required\";b:0;s:17:\"conditional_logic\";b:0;s:7:\"wrapper\";a:3:{s:5:\"width\";s:0:\"\";s:5:\"class\";s:0:\"\";s:2:\"id\";s:0:\"\";}s:13:\"return_format\";s:5:\"array\";}','Tickets','tickets','publish','closed','closed','','field_link','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',12,'https://old.example.com/?post_type=acf-field&p=21',8,'acf-field','',0),(22,0,'2026-11-08 10:05:39','2026-11-08 09:05:39','a:12:{s:10:\"aria-label\";s:0:\"\";s:4:\"type\";s:8:\"repeater\";s:12:\"instructions\";s:0:\"\";s:8:\"required\";b:0;s:17:\"conditional_logic\";b:0;s:7:\"wrapper\";a:3:{s:5:\"width\";s:0:\"\";s:5:\"class\";s:0:\"\";s:2:\"id\";s:0:\"\";}s:3:\"min\";i:0;s:3:\"max\";i:0;s:13:\"rows_per_page\";i:20;s:6:\"layout\";s:5:\"table\";s:12:\"button_label\";s:0:\"\";s:9:\"collapsed\";s:0:\"\";}','Line-up','lineup','publish','closed','closed','','field_lineup','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',12,'https://old.example.com/?post_type=acf-field&p=22',9,'acf-field','',0),(23,0,'2026-11-08 10:05:39','2026-11-08 09:05:39','a:11:{s:10:\"aria-label\";s:0:\"\";s:4:\"type\";s:4:\"text\";s:12:\"instructions\";s:0:\"\";s:8:\"required\";b:0;s:17:\"conditional_logic\";b:0;s:7:\"wrapper\";a:3:{s:5:\"width\";s:0:\"\";s:5:\"class\";s:0:\"\";s:2:\"id\";s:0:\"\";}s:13:\"default_value\";s:0:\"\";s:9:\"maxlength\";s:0:\"\";s:11:\"placeholder\";s:0:\"\";s:7:\"prepend\";s:0:\"\";s:6:\"append\";s:0:\"\";}','Artist','artist','publish','closed','closed','','field_lineup_artist','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',22,'https://old.example.com/?post_type=acf-field&p=23',0,'acf-field','',0),(24,0,'2026-11-08 10:05:39','2026-11-08 09:05:39','a:11:{s:10:\"aria-label\";s:0:\"\";s:4:\"type\";s:10:\"true_false\";s:12:\"instructions\";s:0:\"\";s:8:\"required\";b:0;s:17:\"conditional_logic\";b:0;s:7:\"wrapper\";a:3:{s:5:\"width\";s:0:\"\";s:5:\"class\";s:0:\"\";s:2:\"id\";s:0:\"\";}s:13:\"default_value\";i:0;s:7:\"message\";s:0:\"\";s:2:\"ui\";i:0;s:10:\"ui_on_text\";s:0:\"\";s:11:\"ui_off_text\";s:0:\"\";}','Headliner','headliner','publish','closed','closed','','field_lineup_headliner','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',22,'https://old.example.com/?post_type=acf-field&p=24',1,'acf-field','',0),(25,0,'2026-11-08 10:05:39','2026-11-08 09:05:39','a:10:{s:10:\"aria-label\";s:0:\"\";s:4:\"type\";s:16:\"flexible_content\";s:12:\"instructions\";s:0:\"\";s:8:\"required\";b:0;s:17:\"conditional_logic\";b:0;s:7:\"wrapper\";a:3:{s:5:\"width\";s:0:\"\";s:5:\"class\";s:0:\"\";s:2:\"id\";s:0:\"\";}s:7:\"layouts\";a:2:{s:11:\"layout_text\";a:6:{s:3:\"key\";s:11:\"layout_text\";s:4:\"name\";s:4:\"text\";s:5:\"label\";s:4:\"Text\";s:7:\"display\";s:5:\"block\";s:3:\"min\";s:0:\"\";s:3:\"max\";s:0:\"\";}s:12:\"layout_quote\";a:6:{s:3:\"key\";s:12:\"layout_quote\";s:4:\"name\";s:5:\"quote\";s:5:\"label\";s:5:\"Quote\";s:7:\"display\";s:5:\"block\";s:3:\"min\";s:0:\"\";s:3:\"max\";s:0:\"\";}}s:3:\"min\";s:0:\"\";s:3:\"max\";s:0:\"\";s:12:\"button_label\";s:7:\"Add Row\";}','Sections','sections','publish','closed','closed','','field_sections','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',12,'https://old.example.com/?post_type=acf-field&p=25',10,'acf-field','',0),(26,0,'2026-11-08 10:05:39','2026-11-08 09:05:39','a:12:{s:10:\"aria-label\";s:0:\"\";s:4:\"type\";s:7:\"wysiwyg\";s:12:\"instructions\";s:0:\"\";s:8:\"required\";b:0;s:17:\"conditional_logic\";b:0;s:7:\"wrapper\";a:3:{s:5:\"width\";s:0:\"\";s:5:\"class\";s:0:\"\";s:2:\"id\";s:0:\"\";}s:13:\"parent_layout\";s:11:\"layout_text\";s:4:\"tabs\";s:3:\"all\";s:7:\"toolbar\";s:4:\"full\";s:12:\"media_upload\";i:1;s:13:\"default_value\";s:0:\"\";s:5:\"delay\";i:0;}','Body','body','publish','closed','closed','','field_sections_body','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',25,'https://old.example.com/?post_type=acf-field&p=26',0,'acf-field','',0),(27,0,'2026-11-08 10:05:39','2026-11-08 09:05:39','a:12:{s:10:\"aria-label\";s:0:\"\";s:4:\"type\";s:4:\"text\";s:12:\"instructions\";s:0:\"\";s:8:\"required\";b:0;s:17:\"conditional_logic\";b:0;s:7:\"wrapper\";a:3:{s:5:\"width\";s:0:\"\";s:5:\"class\";s:0:\"\";s:2:\"id\";s:0:\"\";}s:13:\"parent_layout\";s:12:\"layout_quote\";s:13:\"default_value\";s:0:\"\";s:9:\"maxlength\";s:0:\"\";s:11:\"placeholder\";s:0:\"\";s:7:\"prepend\";s:0:\"\";s:6:\"append\";s:0:\"\";}','Quote','quote','publish','closed','closed','','field_sections_quote','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',25,'https://old.example.com/?post_type=acf-field&p=27',0,'acf-field','',0),(28,0,'2026-11-08 10:05:39','2026-11-08 09:05:39','a:12:{s:10:\"aria-label\";s:0:\"\";s:4:\"type\";s:4:\"text\";s:12:\"instructions\";s:0:\"\";s:8:\"required\";b:0;s:17:\"conditional_logic\";b:0;s:7:\"wrapper\";a:3:{s:5:\"width\";s:0:\"\";s:5:\"class\";s:0:\"\";s:2:\"id\";s:0:\"\";}s:13:\"parent_layout\";s:12:\"layout_quote\";s:13:\"default_value\";s:0:\"\";s:9:\"maxlength\";s:0:\"\";s:11:\"placeholder\";s:0:\"\";s:7:\"prepend\";s:0:\"\";s:6:\"append\";s:0:\"\";}','By','by','publish','closed','closed','','field_sections_by','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',25,'https://old.example.com/?post_type=acf-field&p=28',1,'acf-field','',0),(29,0,'2026-11-08 10:05:39','2026-11-08 09:05:39','a:8:{s:10:\"aria-label\";s:0:\"\";s:4:\"type\";s:5:\"group\";s:12:\"instructions\";s:0:\"\";s:8:\"required\";b:0;s:17:\"conditional_logic\";b:0;s:7:\"wrapper\";a:3:{s:5:\"width\";s:0:\"\";s:5:\"class\";s:0:\"\";s:2:\"id\";s:0:\"\";}s:10:\"sub_fields\";a:0:{}s:6:\"layout\";s:5:\"block\";}','Venue','venue','publish','closed','closed','','field_venue','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',12,'https://old.example.com/?post_type=acf-field&p=29',11,'acf-field','',0),(30,0,'2026-11-08 10:05:39','2026-11-08 09:05:39','a:11:{s:10:\"aria-label\";s:0:\"\";s:4:\"type\";s:4:\"text\";s:12:\"instructions\";s:0:\"\";s:8:\"required\";b:0;s:17:\"conditional_logic\";b:0;s:7:\"wrapper\";a:3:{s:5:\"width\";s:0:\"\";s:5:\"class\";s:0:\"\";s:2:\"id\";s:0:\"\";}s:13:\"default_value\";s:0:\"\";s:9:\"maxlength\";s:0:\"\";s:11:\"placeholder\";s:0:\"\";s:7:\"prepend\";s:0:\"\";s:6:\"append\";s:0:\"\";}','Name','name','publish','closed','closed','','field_venue_name','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',29,'https://old.example.com/?post_type=acf-field&p=30',0,'acf-field','',0),(31,0,'2026-11-08 10:05:39','2026-11-08 09:05:39','a:13:{s:10:\"aria-label\";s:0:\"\";s:4:\"type\";s:6:\"number\";s:12:\"instructions\";s:0:\"\";s:8:\"required\";b:0;s:17:\"conditional_logic\";b:0;s:7:\"wrapper\";a:3:{s:5:\"width\";s:0:\"\";s:5:\"class\";s:0:\"\";s:2:\"id\";s:0:\"\";}s:13:\"default_value\";s:0:\"\";s:3:\"min\";s:0:\"\";s:3:\"max\";s:0:\"\";s:4:\"step\";s:0:\"\";s:11:\"placeholder\";s:0:\"\";s:7:\"prepend\";s:0:\"\";s:6:\"append\";s:0:\"\";}','Capacity','capacity','publish','closed','closed','','field_venue_capacity','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',29,'https://old.example.com/?post_type=acf-field&p=31',1,'acf-field','',0),(32,1,'2026-03-01 18:00:00','2026-03-01 17:00:00','<!-- wp:paragraph --><p>Live on stage.</p><!-- /wp:paragraph -->','Summer Concert','','publish','closed','closed','','summer-concert','','','2026-03-01 18:00:00','2026-03-01 17:00:00','',0,'https://old.example.com/veranstaltungen/summer-concert/',0,'event','',0),(33,1,'2026-01-10 10:00:00','2026-01-10 09:00:00','<div class=\"elementor\">Built with a page builder</div>','Landing','','publish','closed','closed','','landing','','','2026-01-10 10:00:00','2026-01-10 09:00:00','',0,'https://old.example.com/landing/',0,'page','',0),(34,1,'2026-01-10 10:00:00','2026-01-10 09:00:00','Not ready yet.','Unfinished','','draft','open','open','','','','','2026-01-10 10:00:00','2026-01-10 09:00:00','',0,'https://old.example.com/?p=34',0,'post','',0),(35,0,'2026-11-08 10:05:39','2026-11-08 09:05:39','','Home','','publish','closed','closed','','home','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',0,'https://old.example.com/2026/11/home/',1,'nav_menu_item','',0),(36,0,'2026-11-08 10:05:39','2026-11-08 09:05:39',' ','','','publish','closed','closed','','36','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',0,'https://old.example.com/2026/11/36/',2,'nav_menu_item','',0),(37,0,'2026-11-08 10:05:39','2026-11-08 09:05:39',' ','','','publish','closed','closed','','37','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',4,'https://old.example.com/2026/11/37/',3,'nav_menu_item','',0),(38,0,'2026-11-08 10:05:39','2026-11-08 09:05:39',' ','','','publish','closed','closed','','38','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',0,'https://old.example.com/2026/11/38/',4,'nav_menu_item','',0),(39,0,'2026-11-08 10:05:39','2026-11-08 09:05:39','','Partner','','publish','closed','closed','','partner','','','2026-11-08 10:05:39','2026-11-08 09:05:39','',0,'https://old.example.com/2026/11/partner/',5,'nav_menu_item','',0),(40,1,'2026-01-10 10:00:00','2026-01-10 09:00:00','<!-- wp:home-link {\"label\":\"Start\"} /--><!-- wp:navigation-link {\"label\":\"About\",\"type\":\"page\",\"id\":4,\"url\":\"https://old.example.com/about/\",\"kind\":\"post-type\"} /--><!-- wp:navigation-submenu {\"label\":\"More\",\"url\":\"#\",\"kind\":\"custom\"} --><!-- wp:navigation-link {\"label\":\"News\",\"type\":\"category\",\"id\":2,\"url\":\"https://old.example.com/?cat=2\",\"kind\":\"taxonomy\"} /--><!-- /wp:navigation-submenu -->','Header navigation','','publish','closed','closed','','header-navigation','','','2026-01-10 10:00:00','2026-01-10 09:00:00','',0,'https://old.example.com/2026/01/header-navigation/',0,'wp_navigation','',0);
/*!40000 ALTER TABLE `wp_posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_term_relationships`
--

DROP TABLE IF EXISTS `wp_term_relationships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_term_relationships` (
  `object_id` bigint unsigned NOT NULL DEFAULT '0',
  `term_taxonomy_id` bigint unsigned NOT NULL DEFAULT '0',
  `term_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`object_id`,`term_taxonomy_id`),
  KEY `term_taxonomy_id` (`term_taxonomy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_term_relationships`
--

LOCK TABLES `wp_term_relationships` WRITE;
/*!40000 ALTER TABLE `wp_term_relationships` DISABLE KEYS */;
INSERT INTO `wp_term_relationships` VALUES (6,2,0),(10,3,0),(10,4,0),(34,1,0),(35,5,0),(36,5,0),(37,5,0),(38,5,0),(39,5,0);
/*!40000 ALTER TABLE `wp_term_relationships` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_term_taxonomy`
--

DROP TABLE IF EXISTS `wp_term_taxonomy`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_term_taxonomy` (
  `term_taxonomy_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `term_id` bigint unsigned NOT NULL DEFAULT '0',
  `taxonomy` varchar(32) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `description` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `parent` bigint unsigned NOT NULL DEFAULT '0',
  `count` bigint NOT NULL DEFAULT '0',
  PRIMARY KEY (`term_taxonomy_id`),
  UNIQUE KEY `term_id_taxonomy` (`term_id`,`taxonomy`),
  KEY `taxonomy` (`taxonomy`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_term_taxonomy`
--

LOCK TABLES `wp_term_taxonomy` WRITE;
/*!40000 ALTER TABLE `wp_term_taxonomy` DISABLE KEYS */;
INSERT INTO `wp_term_taxonomy` VALUES (1,1,'category','',0,0),(2,2,'category','',0,1),(3,3,'category','Gadgets and code',2,1),(4,4,'post_tag','',0,1),(5,5,'nav_menu','',0,5);
/*!40000 ALTER TABLE `wp_term_taxonomy` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_termmeta`
--

DROP TABLE IF EXISTS `wp_termmeta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_termmeta` (
  `meta_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `term_id` bigint unsigned NOT NULL DEFAULT '0',
  `meta_key` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `meta_value` longtext COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`meta_id`),
  KEY `term_id` (`term_id`),
  KEY `meta_key` (`meta_key`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_termmeta`
--

LOCK TABLES `wp_termmeta` WRITE;
/*!40000 ALTER TABLE `wp_termmeta` DISABLE KEYS */;
/*!40000 ALTER TABLE `wp_termmeta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_terms`
--

DROP TABLE IF EXISTS `wp_terms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_terms` (
  `term_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `slug` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `term_group` bigint NOT NULL DEFAULT '0',
  PRIMARY KEY (`term_id`),
  KEY `slug` (`slug`(191)),
  KEY `name` (`name`(191))
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_terms`
--

LOCK TABLES `wp_terms` WRITE;
/*!40000 ALTER TABLE `wp_terms` DISABLE KEYS */;
INSERT INTO `wp_terms` VALUES (1,'Uncategorized','uncategorized',0),(2,'News','news',0),(3,'Tech','tech',0),(4,'PHP','php',0),(5,'Main Menu','main-menu',0);
/*!40000 ALTER TABLE `wp_terms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_usermeta`
--

DROP TABLE IF EXISTS `wp_usermeta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_usermeta` (
  `umeta_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL DEFAULT '0',
  `meta_key` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `meta_value` longtext COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`umeta_id`),
  KEY `user_id` (`user_id`),
  KEY `meta_key` (`meta_key`(191))
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_usermeta`
--

LOCK TABLES `wp_usermeta` WRITE;
/*!40000 ALTER TABLE `wp_usermeta` DISABLE KEYS */;
INSERT INTO `wp_usermeta` VALUES (1,1,'nickname','admin'),(2,1,'first_name',''),(3,1,'last_name',''),(4,1,'description',''),(5,1,'rich_editing','true'),(6,1,'syntax_highlighting','true'),(7,1,'infinite_scrolling','true'),(8,1,'comment_shortcuts','false'),(9,1,'admin_color','modern'),(10,1,'use_ssl','0'),(11,1,'show_admin_bar_front','true'),(12,1,'locale',''),(13,1,'wp_capabilities','a:1:{s:13:\"administrator\";b:1;}'),(14,1,'wp_user_level','10'),(15,1,'dismissed_wp_pointers',''),(16,1,'show_welcome_panel','1'),(17,2,'nickname','editor'),(18,2,'first_name',''),(19,2,'last_name',''),(20,2,'description',''),(21,2,'rich_editing','true'),(22,2,'syntax_highlighting','true'),(23,2,'infinite_scrolling','true'),(24,2,'comment_shortcuts','false'),(25,2,'admin_color','modern'),(26,2,'use_ssl','0'),(27,2,'show_admin_bar_front','true'),(28,2,'locale',''),(29,2,'wp_capabilities','a:1:{s:6:\"editor\";b:1;}'),(30,2,'wp_user_level','7'),(31,2,'dismissed_wp_pointers',''),(32,3,'nickname','author'),(33,3,'first_name',''),(34,3,'last_name',''),(35,3,'description',''),(36,3,'rich_editing','true'),(37,3,'syntax_highlighting','true'),(38,3,'infinite_scrolling','true'),(39,3,'comment_shortcuts','false'),(40,3,'admin_color','modern'),(41,3,'use_ssl','0'),(42,3,'show_admin_bar_front','true'),(43,3,'locale',''),(44,3,'wp_capabilities','a:1:{s:6:\"author\";b:1;}'),(45,3,'wp_user_level','2'),(46,3,'dismissed_wp_pointers',''),(47,4,'nickname','reader'),(48,4,'first_name',''),(49,4,'last_name',''),(50,4,'description',''),(51,4,'rich_editing','true'),(52,4,'syntax_highlighting','true'),(53,4,'infinite_scrolling','true'),(54,4,'comment_shortcuts','false'),(55,4,'admin_color','modern'),(56,4,'use_ssl','0'),(57,4,'show_admin_bar_front','true'),(58,4,'locale',''),(59,4,'wp_capabilities','a:1:{s:10:\"subscriber\";b:1;}'),(60,4,'wp_user_level','0'),(61,4,'dismissed_wp_pointers',''),(62,5,'nickname','member'),(63,5,'first_name',''),(64,5,'last_name',''),(65,5,'description',''),(66,5,'rich_editing','true'),(67,5,'syntax_highlighting','true'),(68,5,'infinite_scrolling','true'),(69,5,'comment_shortcuts','false'),(70,5,'admin_color','modern'),(71,5,'use_ssl','0'),(72,5,'show_admin_bar_front','true'),(73,5,'locale',''),(74,5,'wp_capabilities','a:1:{s:11:\"contributor\";b:1;}'),(75,5,'wp_user_level','1'),(76,5,'dismissed_wp_pointers','');
/*!40000 ALTER TABLE `wp_usermeta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wp_users`
--

DROP TABLE IF EXISTS `wp_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_users` (
  `ID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_login` varchar(60) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `user_pass` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `user_nicename` varchar(50) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `user_email` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `user_url` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `user_registered` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user_activation_key` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `user_status` int NOT NULL DEFAULT '0',
  `display_name` varchar(250) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`ID`),
  KEY `user_login_key` (`user_login`),
  KEY `user_nicename` (`user_nicename`),
  KEY `user_email` (`user_email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wp_users`
--

LOCK TABLES `wp_users` WRITE;
/*!40000 ALTER TABLE `wp_users` DISABLE KEYS */;
INSERT INTO `wp_users` VALUES (1,'admin','$wp$2y$10$puPZz2AtO9p0YDCUzC/SUeAZ7o670BqFLnaB0ItdCJKFo/i/1YUe6','admin','admin@example.com','https://old.example.com','2026-11-08 09:05:30','',0,'admin'),(2,'editor','$wp$2y$10$86VrjLmL1RRIdEj5vEJ3i.KuEB4xWYaUnKSg82dBL8uskI/blWPOO','editor','editor@example.com','','2025-02-01 09:00:00','',0,'Eda Editor'),(3,'author','$P$BDr2J2CzJcTwCckmI61MCl60.rJm4N/','author','author@example.com','','2015-06-01 09:00:00','',0,'Arno Author'),(4,'reader','14b94f2bc9a25d8b183218dd7c45feb8','reader','reader@example.com','','2026-11-08 09:05:39','',0,'Rhea Reader'),(5,'member','$2y$10$1MOzMfElcCYo44IYPLcvYuVvDRVxTFq4UiTb2DAe1O8RO4Mfw/n.2','member','member@example.com','','2026-11-08 09:05:39','',0,'Mo Member');
/*!40000 ALTER TABLE `wp_users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
