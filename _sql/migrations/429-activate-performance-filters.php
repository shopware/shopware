<?php
class Migrations_Migration429 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        if ($modus !== \Shopware\Framework\Migration\AbstractMigration::MODUS_INSTALL) {
            return;
        }

        $sql = <<<'EOD'
UPDATE `s_core_config_elements` SET `value`= 'i:1;' WHERE `name` IN (
    'showSupplierInCategories',
    'displayFiltersInListings',
    'showShippingFreeFacet',
    'showPriceFacet',
    'showVoteAverageFacet',
    'showImmediateDeliveryFacet'
)
EOD;

        $this->addSql($sql);
    }
}
