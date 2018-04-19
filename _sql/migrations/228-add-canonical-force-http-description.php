<?php
class Migrations_Migration228 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
SET @elementId = (SELECT id FROM s_core_config_elements WHERE name ='forceCanonicalHttp' LIMIT 1);
SET @localeID = (SELECT id FROM s_core_locales WHERE locale='en_GB');

UPDATE s_core_config_elements SET description='Diese Option greift nicht, wenn die Option "Überall SSL verwenden" aktiviert ist.' WHERE id=@elementID;
UPDATE s_core_config_element_translations SET description='This option does not take effect if the option "Use always SSL" is activated.' WHERE element_id=@elementID AND locale_id=@localeID;
EOD;

        $this->addSql($sql);
    }
}
