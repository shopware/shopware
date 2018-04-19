<?php
class Migrations_Migration151 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
            UPDATE  s_library_component SET  x_type =  'emotion-components-html-element' WHERE  s_library_component.name = 'HTML-Element';

EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
            UPDATE  s_library_component SET  x_type =  'emotion-components-youtube' WHERE  s_library_component.name = 'Youtube-Video';
EOD;
        $this->addSql($sql);


    }
}
