CREATE TABLE IF NOT EXISTS `data` (
  `data_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `channel` varchar(100) DEFAULT NULL,
  `data` longtext,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`data_id`),
  KEY `channel` (`channel`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;