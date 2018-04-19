<?php
class Migrations_Migration132 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
DELETE FROM s_core_subscribes
WHERE subscribe = 'Shopware_Modules_Marketing_GetSimilarShownArticles'
AND listener = 'Shopware_Plugins_Core_MarketingAggregate_Bootstrap::afterSimilarShownArticlesSelected'
EOD;
        $this->addSql($sql);
    }
}
