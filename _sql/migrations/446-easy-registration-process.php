<?php
class Migrations_Migration446 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        if ($modus !== \Shopware\Framework\Migration\AbstractMigration::MODUS_INSTALL) {
            return;
        }
        
        $sql = <<<'EOD'
            UPDATE `s_core_config_elements` SET value = 'b:0;' 
            WHERE name IN ('doublepasswordvalidation', 'requirePhoneField', 'showphonenumberfield', 'showbirthdayfield');
EOD;
        $this->addSql($sql);
    }
}
