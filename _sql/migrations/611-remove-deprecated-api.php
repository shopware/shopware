<?php

class Migrations_Migration611 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
DELETE FROM s_core_subscribes WHERE listener LIKE "Shopware_Plugins_Core_Api_Bootstrap::%"
EOD;

        $this->addSql($sql);

        $sql = <<<'EOD'
DELETE FROM s_core_plugins WHERE name LIKE "Api" AND author LIKE "shopware AG"
EOD;

        $this->addSql($sql);
    }
}

