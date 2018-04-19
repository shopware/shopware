<?php
class Migrations_Migration488 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
Delete IGNORE FROM `s_search_index`;
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
Delete IGNORE FROM `s_search_keywords`;
EOD;
        $this->addSql($sql);
    }
}
