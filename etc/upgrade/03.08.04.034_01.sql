use ezfw;

DROP TABLE IF EXISTS `offsets`;
DROP TRIGGER IF EXISTS `update_video`;
/*!50003 SET @OLD_SQL_MODE=@@SQL_MODE*/;
DELIMITER ;;

/*!50003 SET SESSION SQL_MODE="" */;;
/*!50003 CREATE */ /*!50017 DEFINER=`root`@`localhost` */ /*!50003 TRIGGER `update_video` BEFORE UPDATE ON `videos` FOR EACH ROW begin IF ( ( NEW.size > OLD.size ) AND find_in_set('current', NEW.flags) ) THEN SET NEW.end=now(); SET NEW.duration = (UNIX_TIMESTAMP(NEW.end) - UNIX_TIMESTAMP(NEW.start)); END IF; END */;;

DELIMITER ;
/*!50003 SET SESSION SQL_MODE=@OLD_SQL_MODE */;

