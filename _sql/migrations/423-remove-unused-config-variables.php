<?php
class Migrations_Migration423 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<SQL
DELETE elems, trans
FROM s_core_config_elements elems
LEFT JOIN s_core_config_element_translations trans
  ON elems.id = trans.element_id
WHERE elems.name IN (
    'articlelimit',
    'configcustomfields',
    'configmaxcombinations',
    'displayFilterArticleCount',
    'ignoreshippingfreeforsurcharges',
    'liveinstock',
    'mailer_encoding',
    'redirectDownload',
    'redirectnotfound',
    'seorelcanonical',
    'seoremovewhitespaces',
    'taxNumber'
)
SQL;

        $this->addSql($sql);
    }
}
