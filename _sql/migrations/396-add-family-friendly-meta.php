<?php
class Migrations_Migration396 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
            SET @parent = (SELECT id FROM s_core_config_forms WHERE name = 'MasterData' LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
            INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES
            (@parent, 'metaIsFamilyFriendly', 'b:1;', 'Shop ist familienfreundlich', 'Setzt den Metatag "isFamilyFriendly" für Suchmaschinen', 'checkbox', 0, 0, 1, NULL, NULL, 'a:0:{}');
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
            SET @newElementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'metaIsFamilyFriendly' LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
            INSERT IGNORE INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`, `description`)
            VALUES (@newElementId, '2', 'Shop is family friendly', 'Will set the meta tag "isFamilyFriendly" for search engines');
EOD;
        $this->addSql($sql);
    }
}
