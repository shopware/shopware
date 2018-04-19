<?php
class Migrations_Migration102 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
ALTER TABLE  `s_emotion` ADD  `grid_id` INT NOT NULL;

DROP TABLE s_emotion_grid;
CREATE TABLE IF NOT EXISTS `s_emotion_grid` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `cols` int(11) NOT NULL,
  `rows` int(11) NOT NULL,
  `cell_height` int(11) NOT NULL,
  `article_height` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;


INSERT INTO `s_emotion_grid` (`id`, `name`, `cols`, `rows`, `cell_height`, `article_height`) VALUES
(1, '3-Spaltig', 3, 20, 185, 2),
(2, '4-Spaltig', 4, 20, 185, 2);


UPDATE s_emotion SET grid_id = 1 WHERE cols = 3;
UPDATE s_emotion SET grid_id = 2 WHERE cols = 4;

ALTER TABLE `s_emotion_grid` ADD `gutter` INT NOT NULL;

UPDATE s_emotion_grid SET gutter = 10;

ALTER TABLE `s_emotion` DROP `template`;
ALTER TABLE  `s_emotion` ADD  `template_id` INT NULL;

UPDATE s_emotion SET template_id = 1;

CREATE TABLE IF NOT EXISTS `s_emotion_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `file` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

INSERT INTO `s_emotion_templates` (`id`, `name`, `file`) VALUES
(1, 'Standard', 'index.tpl');
EOD;

        $this->addSql($sql);
    }
}
