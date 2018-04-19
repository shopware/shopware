<?php

class Migrations_Migration460 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = "SET @formId = (SELECT id FROM s_core_config_forms WHERE name = 'Frontend60' LIMIT 1);";
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE s_core_config_elements
SET `value` = 's:121:"<div>\n<img src=\"{\$sShopURL}/themes/Frontend/Responsive/frontend/_public/src/img/logos/logo--tablet.png\" alt=\"Logo\"><br />";'
WHERE name = 'emailheaderhtml'
AND form_id = @formId;
SQL;

        $this->addSql($sql);
    }

}
