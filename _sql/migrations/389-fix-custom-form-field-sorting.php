<?php
class Migrations_Migration389 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        // Check if the table from the plugin is available
        try {
            $statement = $this->connection->query("SELECT DISTINCT id FROM s_cms_support;");
            $forms = $statement->fetchAll(PDO::FETCH_COLUMN);
        } catch(Exception $e) {
            return;
        }

        foreach($forms as $formId) {
            try {
                $statement = $this->connection->query("SELECT count(DISTINCT id) FROM s_cms_support_fields WHERE position = 0 AND supportID = $formId;");
                $fieldCountArray = $statement->fetch(PDO::FETCH_NUM);
                $fieldCount = array_shift($fieldCountArray);
            } catch(Exception $e) {
                continue;
            }

            if ($fieldCount > 1) {
                $sql = <<<EOD
            SET @position:=0;
            UPDATE s_cms_support_fields SET position = @position:=@position+1 WHERE supportID = $formId;
EOD;
                $this->addSql($sql);
            }
        }
    }
}
