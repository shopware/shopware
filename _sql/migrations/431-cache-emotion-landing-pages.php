<?php
class Migrations_Migration431 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
UPDATE s_core_config_elements SET value = 's:334:"frontend/listing 3600
frontend/index 3600
frontend/detail 3600
frontend/campaign 14400
widgets/listing 14400
frontend/custom 14400
frontend/sitemap 14400
frontend/blog 14400
widgets/index 3600
widgets/checkout 3600
widgets/compare 3600
widgets/emotion 14400
widgets/recommendation 14400
widgets/lastArticles 3600
widgets/campaign 3600";'

WHERE name='cacheControllers';
EOD;
        $this->addSql($sql);
    }
}
