<?php

class Migrations_Migration762 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
ALTER TABLE `s_articles_img` CHANGE `img` `img` VARCHAR(255);
EOD;

        $this->addSql($sql);
    }
}
