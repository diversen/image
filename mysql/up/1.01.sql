CREATE TABLE `content_image` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) NOT NULL,
  `title` varchar (255) NOT NULL,
  `mimetype` varchar (255) NOT NULL,
  `file` mediumblob NOT NULL,
  `abstract` text NOT NULL,
  `published` boolean NOT NULL DEFAULT '0',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=UTF8;