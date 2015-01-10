
-- table
CREATE TEMPORARY TABLE `temp_ef_websites` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `user_id` bigint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
