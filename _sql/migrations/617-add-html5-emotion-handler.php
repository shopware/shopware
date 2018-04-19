<?php

class Migrations_Migration617 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
UPDATE `s_library_component` SET `convert_function` = 'getHtml5Video' WHERE `x_type` = 'emotion-components-html-video';
EOD;

        $this->addSql($sql);
    }
}
