/*
SQLyog Community v13.2.1 (64 bit)
MySQL - 10.4.32-MariaDB : Database - colnect
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`colnect` /*!40100 DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci */;

USE `colnect`;

/*Table structure for table `domain` */

DROP TABLE IF EXISTS `domain`;

CREATE TABLE `domain` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQUE_NAME` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

/*Table structure for table `element` */

DROP TABLE IF EXISTS `element`;

CREATE TABLE `element` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(16) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQUE_NAME` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

/*Table structure for table `request` */

DROP TABLE IF EXISTS `request`;

CREATE TABLE `request` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `domain_id` int(11) DEFAULT NULL,
  `url_id` int(11) DEFAULT NULL,
  `element_id` int(11) DEFAULT NULL,
  `time` timestamp NOT NULL DEFAULT current_timestamp(),
  `duration` int(11) DEFAULT NULL,
  `count` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `domain_id` (`domain_id`),
  KEY `url_id` (`url_id`),
  KEY `element_id` (`element_id`),
  CONSTRAINT `request_ibfk_1` FOREIGN KEY (`domain_id`) REFERENCES `domain` (`id`),
  CONSTRAINT `request_ibfk_2` FOREIGN KEY (`url_id`) REFERENCES `url` (`id`),
  CONSTRAINT `request_ibfk_3` FOREIGN KEY (`element_id`) REFERENCES `element` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

/*Table structure for table `url` */

DROP TABLE IF EXISTS `url`;

CREATE TABLE `url` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(2048) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQUE_NAME` (`name`) USING HASH
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

/* Procedure structure for procedure `create_log` */

/*!50003 DROP PROCEDURE IF EXISTS  `create_log` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `create_log`(
	    IN p_url VARCHAR(2048),
	    IN p_domain VARCHAR(128),
	    IN p_tag VARCHAR(16),
	    IN p_time INT,
	    IN p_count INT
    )
    MODIFIES SQL DATA
BEGIN
    DECLARE url_id INT;
    DECLARE domain_id INT;
    DECLARE element_id INT;

    -- Insert the URL if it does not exist
    INSERT INTO url (`name`)
    VALUES (p_url)
    ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);

    -- Get the ID of the inserted or existing URL
    SELECT id INTO url_id
    FROM url
    WHERE `name` = p_url;

    -- Insert the domain if it does not exist
    INSERT INTO domain (`name`)
    VALUES (p_domain)
    ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);

    -- Get the ID of the inserted or existing domain
    SELECT id INTO domain_id
    FROM domain
    WHERE `name` = p_domain;

    -- Insert the tag if it does not exist
    INSERT INTO element (`name`)
    VALUES (p_tag)
    ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);

    -- Get the ID of the inserted or existing tag
    SELECT id INTO element_id
    FROM element
    WHERE `name` = p_tag;

    -- Insert into request table if all IDs are greater than 0
    IF url_id > 0 AND domain_id > 0 AND element_id > 0 THEN
        INSERT INTO request (domain_id, url_id, element_id, `duration`, `count`)
        VALUES (domain_id, url_id, element_id, p_time, p_count);
    END IF;
END */$$
DELIMITER ;

/* Procedure structure for procedure `get_stats` */

/*!50003 DROP PROCEDURE IF EXISTS  `get_stats` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `get_stats`(
	    IN p_domain VARCHAR(128),
	    IN p_tag VARCHAR(16),
	    IN p_hours INT
    )
    READS SQL DATA
BEGIN
	    DECLARE l_domain_id INT DEFAULT 0;
	    DECLARE l_element_id INT DEFAULT 0;
	    DECLARE o_different INT DEFAULT 0;
	    DECLARE o_average FLOAT DEFAULT 0;
	    DECLARE o_sub_count INT DEFAULT 0;
	    DECLARE o_total_count INT DEFAULT 0;

	    -- Get the domain ID
	    SELECT id INTO l_domain_id
	    FROM domain
	    WHERE `name` = p_domain;

	    -- Get the element ID
	    SELECT id INTO l_element_id
	    FROM element
	    WHERE `name` = p_tag;

	    IF l_domain_id > 0 AND l_element_id > 0 THEN
		    -- Count distinct URLs
		    SELECT COUNT(DISTINCT url_id) INTO o_different
		    FROM request
		    WHERE domain_id = l_domain_id;

		    -- Calculate the average request time in the last p_hours
		    SELECT CAST(AVG(duration) AS INT) INTO o_average
		    FROM request
		    WHERE domain_id = l_domain_id
		      AND `time` > UNIX_TIMESTAMP(NOW() - INTERVAL p_hours HOUR);

		    -- Calculate the sum of counts for the specified element and domain, with distinct url_id
		    SELECT SUM(t.max_count) INTO o_sub_count
		    FROM (
			SELECT MAX(`count`) AS max_count
			FROM request
			WHERE domain_id = l_domain_id
			  AND element_id = l_element_id
			GROUP BY url_id
		    ) AS t;
		    
		    SELECT SUM(t.max_count) INTO o_total_count
		    FROM (
			SELECT MAX(`count`) AS max_count
			FROM request
			WHERE element_id = l_element_id
			GROUP BY url_id
		    ) AS t;
	    END IF;
	    
	    -- Return the results
	    SELECT o_different AS different_urls, o_average AS average_time, o_sub_count AS sub_count, o_total_count AS total_count;

	END */$$
DELIMITER ;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
