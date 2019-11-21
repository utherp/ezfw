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

use ezfw;


--
-- clear table first...
--
delete from `date_records`;

--
-- Populate date_records
--
-- add events
insert into `date_records` (`year`, `month`, `day`, `event`) select substr(`time`,  1, 4) as `year`, substr(`time`,  6, 2) as `month`, substr(`time`,  9, 2) as `day`, `id` from `events`;
-- add videos
insert into `date_records` (`year`, `month`, `day`, `video`) select substr(`start`, 1, 4) as `year`, substr(`start`, 6, 2) as `month`, substr(`start`, 9, 2) as `day`, `id` from `videos`;
-- add states
insert into `date_records` (`year`, `month`, `day`, `state`) select substr(`start`, 1, 4) as `year`, substr(`start`, 6, 2) as `month`, substr(`start`, 9, 2) as `day`, `id` from `states`;


--
-- add videos which pass more than one day
-- NOTE: this only works to add the ending day if different from the starting day
-- ... this will not add any potential days between the starting and ending days
-- so the data may not be completely accurate upon update, however, the triggers
-- work as they should, adding an entry for each day for which a video or state exists.
--
insert into `date_records` (`year`, `month`, `day`, `video`) select substr(`end`,   1, 4) as `year`, substr(`end`,   6, 2) as `month`, substr(`end`,   9, 2) as `day`, `id` from `videos` where substr(`start`, 1, 10) != substr(`end`, 1, 10);
insert into `date_records` (`year`, `month`, `day`, `state`) select substr(`end`,   1, 4) as `year`, substr(`end`,   6, 2) as `month`, substr(`end`,   9, 2) as `day`, `id` from `states` where substr(`start`, 1, 10) != substr(`end`, 1, 10);


/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2010-01-21 18:28:03
