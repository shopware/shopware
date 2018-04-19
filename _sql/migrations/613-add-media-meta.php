<?php

class Migrations_Migration613 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
ALTER TABLE `s_media` ADD `width` INT(11) UNSIGNED NULL AFTER `file_size`, ADD `height` INT(11) UNSIGNED NULL AFTER `width`;
EOD;

        $this->addSql($sql);
    }
}
