<?php

class Migrations_Migration901 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql('ALTER TABLE `s_articles_vote` ADD `shop_id` INT NULL DEFAULT NULL;');
        $this->addSql("SET @formId = (SELECT id FROM s_core_config_forms WHERE name = 'Rating' LIMIT 1)");
        $this->addSql("ALTER TABLE `s_articles_vote` CHANGE `answer_date` `answer_date` DATETIME NULL DEFAULT NULL;");

        $sql = <<<'EOD'

INSERT IGNORE INTO `s_core_config_elements`
  (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`)
VALUES
(@formId, 'displayOnlySubShopVotes', 'b:0;', 'Nur Subshopspezifische Bewertungen anzeigen', 'description', 'checkbox', 0, 0, 1);
EOD;

        $this->addSql($sql);

        $sql = <<<'EOD'
        SET @elementId = (SELECT id FROM s_core_config_elements WHERE name = 'displayOnlySubShopVotes' LIMIT 1);
        INSERT INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`) VALUES (@elementId, 2, 'Display shop specific votes only', NULL);
EOD;

        $this->addSql($sql);
        $this->addSql("UPDATE s_core_config_elements SET scope = 1 WHERE name = 'votedisable'");
    }
}
