<?php

class Migrations_Migration788 extends Shopware\Framework\Migration\AbstractMigration
{
    /**
     * @param string $modus
     * @return void
     */
    public function up($modus)
    {
        $sql = <<<'EOD'
           UPDATE `s_core_config_elements` SET `label` = 'prev/next-Tag auf paginierten Seiten benutzen',
           `description` = 'Wenn aktiv, wird auf paginierten Seiten anstatt des Canoncial-Tags der prev/next-Tag benutzt.'
           WHERE `s_core_config_elements`.`name` = 'seoIndexPaginationLinks';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
           SET @elementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'seoIndexPaginationLinks' LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
           UPDATE `s_core_config_element_translations` SET `label` = 'Use prev/next-tag on paginated sites',
           `description` = 'If active, use prev/next-tag instead of the Canoncial-tag on paginated sites'
           WHERE `element_id` = @elementId;
EOD;
        $this->addSql($sql);
    }
}
