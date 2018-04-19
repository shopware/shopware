<?php
class Migrations_Migration409 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<EOD
ALTER TABLE `s_filter_values` ADD `media_id` INT NULL DEFAULT NULL , ADD INDEX (`media_id`) ;
EOD;
        $this->addSql($sql);

        $sql = <<<EOD
INSERT IGNORE INTO `s_core_config_elements` (`id`, `form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES
(NULL, 0, 'showImmediateDeliveryFacet', 'i:0;', '', '', 'boolean', 1, 0, 0, NULL, NULL, NULL),
(NULL, 0, 'showShippingFreeFacet',      'i:0;', '', '', 'boolean', 1, 0, 0, NULL, NULL, NULL),
(NULL, 0, 'showPriceFacet',             'i:0;', '', '', 'boolean', 1, 0, 0, NULL, NULL, NULL),
(NULL, 0, 'showVoteAverageFacet',       'i:0;', '', '', 'boolean', 1, 0, 0, NULL, NULL, NULL),
(NULL, 0, 'defaultListingSorting',      'i:1;', '', '', '', 1, 0, 0, NULL, NULL, NULL);
EOD;

        $this->addSql($sql);

        $sql = "DELETE FROM s_core_config_elements WHERE name = 'orderbydefault'";
        $this->addSql($sql);

        $sql = "UPDATE s_filter SET sortmode = 0 WHERE sortmode = 2;";
        $this->addSql($sql);
    }
}