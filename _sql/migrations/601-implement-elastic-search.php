<?php

class Migrations_Migration601 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
CREATE TABLE IF NOT EXISTS `s_es_backlog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1 ;

EOD;
        $this->addSql($sql);

        $sql = "
            ALTER TABLE s_articles_categories_ro
            ADD INDEX `elastic_search` (`categoryID`,`articleID`);
        ";
        $this->addSql($sql);

        $sql = "
            INSERT INTO `s_core_config_elements` (`id`, `form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`)
            VALUES (NULL, '0', 'lastBacklogId', 'i:0;', '', 'Last processed backlog id', '', '0', '0', '0', NULL, NULL)
        ";
        $this->addSql($sql);

        $sql = "
            SET @formId = (SELECT id FROM s_core_config_forms WHERE name = 'Search' LIMIT 1);
        ";
        $this->addSql($sql);

        $sql = "
INSERT INTO `s_core_config_elements` (`id`, `form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES
(NULL, @formId, 'activateNumberSearch', 'i:1;', 'Nummern Suche aktivieren', NULL, 'checkbox', 1, 0, 0, NULL, NULL, NULL);
        ";
        $this->addSql($sql);
    }
}
