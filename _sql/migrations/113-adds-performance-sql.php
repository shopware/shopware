<?php
class Migrations_Migration113 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
INSERT IGNORE INTO `s_core_config_elements`
  (`name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`)
VALUES
('topSellerActive', 'i:1;', '', '', '', 1, 0, 0, NULL, NULL, ''),
('topSellerValidationTime', 'i:100;', '', '', '', 1, 0, 0, NULL, NULL, ''),
('topSellerRefreshStrategy', 'i:3;', '', '', '', 1, 0, 0, NULL, NULL, ''),
('topSellerPseudoSales', 'i:1;', '', '', '', 1, 0, 0, NULL, NULL, ''),
('seoRefreshStrategy', 'i:3;', '', '', '', 1, 0, 0, NULL, NULL, ''),
('searchRefreshStrategy', 'i:3;', '', '', '', 1, 0, 0, NULL, NULL, ''),
('showSupplierInCategories', 'i:1;', '', '', '', 1, 0, 0, NULL, NULL, ''),
('propertySorting', 'i:1;', '', '', '', 1, 0, 0, NULL, NULL, ''),
('disableShopwareStatistics', 'i:0;', '', '', '', 1, 0, 0, NULL, NULL, ''),
('disableArticleNavigation', 'i:0;', '', '', '', 1, 0, 0, NULL, NULL, ''),
('similarRefreshStrategy', 'i:3;', '', '', '', 1, 0, 0, NULL, NULL, ''),
('similarActive', 'i:1;', '', '', '', 1, 0, 0, NULL, NULL, ''),
('similarValidationTime', 'i:100;', '', '', '', 1, 0, 0, NULL, NULL, '');
EOD;

        $this->addSql($sql);
    }
}
