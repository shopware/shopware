<?php
class Migrations_Migration751 extends Shopware\Framework\Migration\AbstractMigration
{
    /**
     * @param string $modus
     * @return void
     */
    public function up($modus)
    {
        $sql = <<<'EOD'
ALTER TABLE `s_core_menu`
DROP `hyperlink`,
DROP `style`,
DROP `resourceID`;
EOD;
        $this->addSql($sql);
    }
}
