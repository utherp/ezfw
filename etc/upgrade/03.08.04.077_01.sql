use ezfw;

ALTER TABLE `videos` ADD INDEX (`size`), ADD INDEX (`flags`);
ALTER TABLE `events` ADD INDEX (`time`);
ALTER TABLE `states` ADD INDEX (`end`);

