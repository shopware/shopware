<?php
class Migrations_Migration107 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
SET @formId = (SELECT id FROM `s_core_config_forms` WHERE `name` LIKE 'QueryCache');

DELETE ce,cet
FROM s_core_config_elements ce
LEFT JOIN s_core_config_element_translations cet
ON cet.element_id = ce.id
WHERE ce.form_Id = @formId
AND ce.name IN (
  "cacheprices",
  "cachechart",
  "cachetranslations",
  "cachearticle",
  "cachecategory",
  "cachecountries",
  "cachetranslations",
  "cachesupplier",
  "deletecacheafterorder",
  "disablecache"
);
EOD;

        $this->addSql($sql);
    }
}
