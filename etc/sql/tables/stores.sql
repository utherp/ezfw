-- MySQL dump 10.11
--
-- Host: localhost    Database: ezfw
-- ------------------------------------------------------
-- Server version    5.0.32-Debian_7etch8-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `stores`
--
use ezfw;

CREATE TABLE `stores` (
  `tag` varchar(30) NOT NULL,
  `class` varchar(100) NOT NULL,
  `manager_pid` int(11) default NULL,
  `device_tag` varchar(100) NOT NULL default 'video',
  `path` varchar(1024) NOT NULL,
  `device_usage_ratio` float NOT NULL default '0',
  `export_ratio` float NOT NULL default '0.25' COMMENT 'Export out of store when free space is at this ratio from max_usage_bytes',
  `safe_ratio` float NOT NULL default '0.3' COMMENT 'Stop exporting when free space is at or above this ratio from max_usage_bytes',
  `lowest_import_weight` float NOT NULL default '0' COMMENT 'Lowest weight a video will score before the store will import it (the lowest weight of the most recent set of exported videos)',
  `max_usage_bytes` bigint(20) NOT NULL default '50000000000',
  `size` bigint(20) NOT NULL default '0',
  `flags` set('recalc','managed','locked','moving','local') NOT NULL default 'recalc,local',
  `name` varchar(100) NOT NULL,
  `description` text,
  KEY `class` (`class`),
  KEY `tag` (`tag`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Dumping data for table `stores`
--

LOCK TABLES `stores` WRITE;
/*!40000 ALTER TABLE `stores` DISABLE KEYS */;
INSERT INTO `stores` VALUES
    ('buffer','bufferStore',NULL,'flash','/usr/local/ezfw/video/buffer/',1,0.4,0.5,0,1600000000,0,'recalc,local','Buffer Storage','Initial store for new video being recorded'),
    ('history','historyStore',NULL,'video','/usr/local/ezfw/video/history/',0.12,0.015,0.025,0,70000000000,0,'recalc,local','History Storage','Stores video younger than a given age'),
    ('archive3M','archiveStore3M',NULL,'video','/usr/local/ezfw/video/archive/3M/',0.85,0.02,0.04,0,180000000000,0,'recalc,local','Archival 3 Month Storage','Stores video based on the weighted scored of all events and states'),
    ('archive6M','archiveStore6M',NULL,'video','/usr/local/ezfw/video/archive/6M/',0.85,0.02,0.04,0,100000000000,0,'recalc,local','Archival 6 Month Storage','Stores video based on the weighted scored of all events and states'),
    ('archive9M','archiveStore9M',NULL,'video','/usr/local/ezfw/video/archive/9M/',0.85,0.02,0.04,0,50000000000,0,'recalc,local','Archival 9 Month Storage','Stores video based on the weighted scored of all events and states'),
    ('archivePerm','archiveStorePerm',NULL,'video','/usr/local/ezfw/video/archive/Perm/',0.85,0.02,0.04,0,40000000000,0,'recalc,local','Archival Historic Storage','Stores video based on the weighted scored of all events and states'),
    ('server','serverStore',NULL,'server','',0,-1,-1,0,20000000000,0,'recalc','Server Storage','For storing video on a remote server, typically for investigation or if something catastrophic occurs (loss of drive, unexpected weighing malfunction, ect..)'),
    ('purge','purgeStore',NULL,'video','/usr/local/ezfw/video/purge/',0.03,0.15,0.7,0,4000000000,0,'recalc,local','Purging Store','This store always scores 0, it is where video goes when other stores score a video too low to store themselves based on storage use.  When a video enters the purging store, they do not get rescored move again, instead they are deleted.');
/*!40000 ALTER TABLE `stores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `storage_devices`
--

CREATE TABLE `storage_devices` (
  `tag` varchar(100) NOT NULL,
  `uuid` varchar(40) default NULL,
  `block_size` int(11) NOT NULL default '4096',
  `blocks` int(11) NOT NULL default '0',
  `total_size` bigint(20) NOT NULL default '0',
  `total_usage` bigint(20) NOT NULL default '0',
  `max_usage_ratio` float NOT NULL default '0.9',
  `mountpoint` varchar(1024) default NULL,
  PRIMARY KEY  (`tag`),
  KEY `tag` (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2010-01-21 18:26:38
