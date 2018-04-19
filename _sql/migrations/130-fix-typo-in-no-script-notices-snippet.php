<?php
class Migrations_Migration130 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
UPDATE `s_core_snippets` SET `value` = 'Um {$sShopname} in vollem Umfang nutzen zu k&ouml;nnen, empfehlen wir Ihnen Javascript in Ihrem Browser zu aktiveren.' WHERE `name` = 'IndexNoscriptNotice' AND `value` = 'Um {$sShopname} in vollen Umfang nutzen zu k&ouml;nnen, empfehlen wir Ihnen Javascript in Ihren Browser zu aktiveren.';
EOD;
        $this->addSql($sql);
    }
}
