ALTER TABLE `image` ADD `file_thumb` mediumblob NOT NULL;

ALTER TABLE `image` ADD `file_org` mediumblob NOT NULL;

CREATE INDEX `parent_id_index` ON `image` (parent_id);
