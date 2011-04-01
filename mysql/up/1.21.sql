RENAME TABLE `content_image` TO `image`;

ALTER TABLE `image` ADD `reference` varchar(255) NOT NULL;

CREATE INDEX `reference_index` ON `image` (reference);