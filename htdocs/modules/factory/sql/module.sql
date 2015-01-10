
-- table
CREATE TEMPORARY TABLE `temp_ef_websites` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `user_id` bigint NOT NULL DEFAULT '0',
  `cdt` bigint NOT NULL,
  `dt` bigint NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `cdt` (`cdt`),    
  KEY `dt` (`dt`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;


-- table
CREATE TEMPORARY TABLE `temp_ef_commands` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL DEFAULT '0',
  `ef_website_id` bigint NOT NULL DEFAULT '0',
  `command` text NOT NULL,
  `payload` mediumtext NOT NULL,
  `response` mediumtext NOT NULL,
  `status` int NOT NULL,
  `sdt` bigint NOT NULL,  
  `edt` bigint NOT NULL,
  `cdt` bigint NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `ef_website_id` (`ef_website_id`),
  KEY `status` (`status`),
  KEY `sdt` (`sdt`),    
  KEY `edt` (`edt`),    
  KEY `cdt` (`cdt`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
