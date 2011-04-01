ALTER TABLE `image` DROP INDEX `reference_index`;

ALTER TABLE `image` DROP COLUMN `reference`;

RENAME TABLE `image` TO `content_image`;