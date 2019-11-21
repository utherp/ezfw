-- MySQL dump 10.11
--
-- Host: localhost    Database: ezfw
-- ------------------------------------------------------
-- Server version   5.0.32-Debian_7etch8-log

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

/*!50003 SET @OLD_SQL_MODE=@@SQL_MODE*/;

use ezfw;

DELIMITER ;;
/*!50003 SET SESSION SQL_MODE="" */;;
--
-- All the following triggers add items to the date_records table
--

--
-- this trigger adds new event ids
--
/*!50003 CREATE TRIGGER `add_event_date_record` AFTER INSERT ON `events` FOR EACH ROW BEGIN INSERT INTO `date_records` (`year`, `month`, `day`, `event`) values (substr(NEW.time, 1, 4), substr(NEW.time, 6, 2), substr(NEW.time, 9, 2), NEW.id); END;;

--
-- this trigger adds new video ids
--
/*!50003 CREATE TRIGGER `add_video_date_record` AFTER INSERT ON `videos` FOR EACH ROW BEGIN INSERT INTO `date_records` (`year`, `month`, `day`, `video`) values (substr(NEW.start, 1, 4), substr(NEW.start, 6, 2), substr(NEW.start, 9, 2), NEW.id); END */;;

--
-- this trigger adds new state ids
--
/*!50003 CREATE TRIGGER `add_state_date_record` AFTER INSERT ON `states` FOR EACH ROW BEGIN INSERT INTO `date_records` (`year`, `month`, `day`, `state`) values (substr(NEW.start, 1, 4), substr(NEW.start, 6, 2), substr(NEW.start, 9, 2), NEW.id); END;;

--
-- this trigger adds updated video ids if the ending time has been updated for a new day
--
/*!50003 CREATE TRIGGER `update_video_date_record` AFTER UPDATE ON `videos` FOR EACH ROW BEGIN IF NEW.end IS NOT NULL AND NEW.end != OLD.end AND substr(NEW.end, 1, 10) != substr(NEW.end, 1, 10) AND substr(OLD.end, 1, 10) != substr(NEW.end, 1, 10) THEN INSERT INTO `date_records` (`year`, `month`, `day`, `video`) values (substr(NEW.end, 1, 4), substr(NEW.end, 6, 2), substr(NEW.end, 9, 2), NEW.id); END IF; END */;;

--
-- this trigger adds updated state ids if the ending time has been updated for a new day
--
/*!50003 CREATE TRIGGER `update_state_date_record` AFTER UPDATE ON `states` FOR EACH ROW BEGIN IF NEW.end IS NOT NULL AND NEW.end != OLD.end AND substr(NEW.start, 1, 10) != substr(NEW.end, 1, 10) AND substr(OLD.end, 1, 10) != substr(NEW.end, 1, 10) THEN INSERT INTO `date_records` (`year`, `month`, `day`, `state`) values (substr(NEW.end, 1, 4), substr(NEW.end, 6, 2), substr(NEW.end, 9, 2), NEW.id); END IF; END */;;


DELIMITER ;
/*!50003 SET SESSION SQL_MODE=@OLD_SQL_MODE */;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2010-01-21 18:28:03
