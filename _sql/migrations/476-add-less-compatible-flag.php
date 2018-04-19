<?php

class Migrations_Migration476 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $statement = $this->getConnection()->prepare('SHOW COLUMNS FROM `s_core_templates_config_elements`;');
        $statement->execute();
        $result = $statement->fetchAll(\PDO::FETCH_COLUMN);

        if (!in_array('less_compatible', $result)) {
            $this->addLessCompatibleFlag();
        }
    }

    private function addLessCompatibleFlag()
    {
        $sql = <<<SQL
ALTER TABLE `s_core_templates_config_elements` ADD `less_compatible` INT(1) NOT NULL DEFAULT '1' ;
SQL;
        $this->addSql($sql);
    }
}
