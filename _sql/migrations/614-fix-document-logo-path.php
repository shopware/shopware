<?php

class Migrations_Migration614 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
UPDATE s_core_documents_box
SET value = REPLACE(value, 'http://www.shopware.de/logo/logo.png ', 'http://www.shopware.de/logo/logo.png')
WHERE value LIKE '%http://www.shopware.de/logo/logo.png %'
EOD;

        $this->addSql($sql);
    }
}
