<?php
class Migrations_Migration118 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
ALTER TABLE `s_filter_relations` ADD INDEX  `get_set_assigns_query` (  `groupID`, `position` );
ALTER TABLE `s_filter` ADD INDEX  `get_sets_query` (  `position` );
ALTER TABLE `s_filter_options` ADD INDEX  `get_options_query` (  `name` );
ALTER TABLE `s_filter_values` ADD INDEX  `get_property_value_by_option_id_query` (  `optionID` ,  `position` );
EOD;

        $this->addSql($sql);
    }
}
