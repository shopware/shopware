<?php
class Migrations_Migration351 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
            SET @parent = (SELECT id FROM `s_core_menu` WHERE `name`= 'Einstellungen');

            INSERT INTO `s_core_menu` (`id`, `parent`, `hyperlink`, `name`,`onclick`, `style`,`class`,`position` ,`active` ,`pluginID` ,`resourceID` ,`controller` ,`shortcut` ,`action`)
            VALUES (NULL ,  @parent,  '',  'Theme Manager 2.0', NULL , NULL ,  'sprite-application-icon-large',  '0',  '1', NULL , NULL ,  'Theme', NULL ,  'Index');
EOD;
        $this->addSql($sql);
    }
}



