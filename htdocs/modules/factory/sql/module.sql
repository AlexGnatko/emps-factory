
-- table
CREATE TEMPORARY TABLE `temp_ef_websites` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `user_id` bigint NOT NULL DEFAULT '0',
  `parent_website_id` bigint NOT NULL DEFAULT '0',
  `status` bigint NOT NULL,
  `cdt` bigint NOT NULL,
  `dt` bigint NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `parent_website_id` (`parent_website_id`),
  KEY `status` (`status`),
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

-- table
CREATE TEMPORARY TABLE `temp_ef_heartbeat` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL DEFAULT '0',
  `ef_website_id` bigint NOT NULL DEFAULT '0',
  `status` int NOT NULL,
  `nedt` bigint NOT NULL,  
  `cdt` bigint NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `ef_website_id` (`ef_website_id`),
  KEY `status` (`status`),
  KEY `nedt` (`nedt`),    
  KEY `cdt` (`cdt`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- table
CREATE TEMPORARY TABLE `temp_ef_stats` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL DEFAULT '0',
  `ef_website_id` bigint NOT NULL DEFAULT '0',
  `status` int NOT NULL,
  `nedt` bigint NOT NULL,
  `cdt` bigint NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `ef_website_id` (`ef_website_id`),
  KEY `status` (`status`),
  KEY `nedt` (`nedt`),
  KEY `cdt` (`cdt`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- table
CREATE TEMPORARY TABLE `temp_ef_stats_values` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `stats_id` bigint NOT NULL DEFAULT '0',
  `period` int NOT NULL,
  `code` varchar(255) NOT NULL,
  `value` bigint NOT NULL,
  `dt` bigint NOT NULL,
  `cdt` bigint NOT NULL,
  PRIMARY KEY (`id`),
  KEY `stats_id` (`stats_id`),
  KEY `period` (`period`),
  KEY `code` (`code`),
  KEY `value` (`value`),
  KEY `dt` (`dt`),
  KEY `cdt` (`cdt`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- table
CREATE TEMPORARY TABLE `temp_ef_database_stats_values` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `database` varchar(255) NOT NULL,
  `period` bigint NOT NULL,
  `code` varchar(255) NOT NULL,
  `value` bigint NOT NULL,
  `dt` bigint NOT NULL,
  `cdt` bigint NOT NULL,
  PRIMARY KEY (`id`),
  KEY `database` (`database`),
  KEY `period` (`period`),
  KEY `code` (`code`),
  KEY `value` (`value`),
  KEY `dt` (`dt`),
  KEY `cdt` (`cdt`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- table
CREATE TEMPORARY TABLE `temp_ef_remote_commands` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  `apikey` varchar(255) NOT NULL,
  `command` text NOT NULL,
  `status` int NOT NULL,
  `cdt` bigint NOT NULL,
  `dt` bigint NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `url` (`url`),
  KEY `name` (`name`),
  KEY `apikey` (`apikey`),
  KEY `status` (`status`),
  KEY `dt` (`dt`),
  KEY `cdt` (`cdt`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- table
CREATE TEMPORARY TABLE `temp_ef_services` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL DEFAULT '0',
  `website_id` bigint NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL,
  `path` text NOT NULL,
  `command` text NOT NULL,
  `lastrun` bigint NOT NULL,
  `lastpid` bigint NOT NULL,
  `runcmd` text NOT NULL,
  `status` int NOT NULL,
  `cdt` bigint NOT NULL,
  `dt` bigint NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `website_id` (`website_id`),
  KEY `name` (`name`),
  KEY `lastrun` (`lastrun`),
  KEY `lastpid` (`lastpid`),
  KEY `status` (`status`),
  KEY `dt` (`dt`),
  KEY `cdt` (`cdt`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
