<?php

class Migrations_Migration748 extends Shopware\Framework\Migration\AbstractMigration
{
    /**
     * @param string $modus
     * @return void
     */
    public function up($modus)
    {
        $sql = <<<'EOD'
ALTER TABLE `s_core_config_elements`
DROP `filters`,
DROP `validators`;
EOD;

        $this->addSql($sql);

        $sql = <<<'EOD'
ALTER TABLE `s_core_config_forms`
DROP `scope`;
EOD;

        $this->addSql($sql);
    }
}
