<?php

use Shopware\Framework\Migration\AbstractMigration;

class Migrations_Migration911 extends AbstractMigration
{
    /**
     * @inheritdoc
     */
    public function up($modus)
    {
        $sql = <<<'SQL'
INSERT INTO s_core_config_elements (form_id, name, value, label, description, type, required, position, scope, options)
VALUES (0, 'listingMode', 's:16:"full_page_reload";', '', '', 'listing-filter-mode-select', 1, 0, 0, NULL);
SQL;
        $this->addSql($sql);
    }
}
