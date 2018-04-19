<?php
class Migrations_Migration317 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
            UPDATE `s_core_config_elements`
            SET `value` = 's:34:"http://store.shopware.com/storeApi";'
            WHERE `value` = 's:33:"http://store.shopware.de/storeApi";'
            AND `name` = 'StoreApiUrl';
EOD;

        $this->addSql($sql);

        $sql = <<<'EOD'
            UPDATE `s_core_config_elements`
            SET `value` = 's:72:"http://store.shopware.com/downloads/free/plugin/%name%/version/%version%";'
            WHERE `value` = 's:71:"http://store.shopware.de/downloads/free/plugin/%name%/version/%version%";'
            AND `name` = 'DummyPluginUrl';
EOD;

        $this->addSql($sql);

        $sql = <<<'EOD'
            UPDATE `s_core_config_values`
            SET `value` = 's:34:"http://store.shopware.com/storeApi";'
            WHERE `value` = 's:33:"http://store.shopware.de/storeApi";'
            AND `element_id` = (SELECT `id` FROM `s_core_config_elements` WHERE `name` = 'StoreApiUrl' LIMIT 1);
EOD;

        $this->addSql($sql);

        $sql = <<<'EOD'
            UPDATE `s_core_config_values`
            SET `value` = 's:72:"http://store.shopware.com/downloads/free/plugin/%name%/version/%version%";'
            WHERE `value` = 's:71:"http://store.shopware.de/downloads/free/plugin/%name%/version/%version%";'
            AND `element_id` = (SELECT `id` FROM `s_core_config_elements` WHERE `name` = 'DummyPluginUrl' LIMIT 1);
EOD;

        $this->addSql($sql);

    }
}
