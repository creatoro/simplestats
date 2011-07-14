CREATE TABLE IF NOT EXISTS `stats` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `counter_daily` int(11) UNSIGNED NOT NULL DEFAULT '0',
  `counter_sum` int(11) UNSIGNED NOT NULL DEFAULT '0',
  `created` int(10) UNSIGNED DEFAULT NULL,
  `updated` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `stats_history` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `stat_id` int(11) UNSIGNED NOT NULL,
  `counter` int(11) UNSIGNED NOT NULL,
  `date` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

ALTER TABLE `stats_history`
  ADD CONSTRAINT `stats_history_ibfk_1` FOREIGN KEY (`stat_id`) REFERENCES `stats` (`id`) ON DELETE CASCADE;