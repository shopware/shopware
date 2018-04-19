<?php
class Migrations_Migration138 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
UPDATE `s_core_snippets` SET `value` = 'Zuletzt angesehen' WHERE `name` = 'WidgetsRecentlyViewedHeadline' AND `value` = 'Zuletzt angeschaute Artikel';
EOD;
        $this->addSql($sql);
    }
}
