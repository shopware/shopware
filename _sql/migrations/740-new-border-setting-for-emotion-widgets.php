<?php

class Migrations_Migration740 extends Shopware\Framework\Migration\AbstractMigration
{
    /**
     * @param string $modus
     * @return void
     */
    public function up($modus)
    {
        $components = [
            'emotion-components-article',
            'emotion-components-article-slider',
            'emotion-components-manufacturer-slider'
        ];

        $sql = <<<'EOD'
INSERT IGNORE INTO s_library_component_field
(componentID, name, x_type, allow_blank, position)
SELECT
    id as componentID,
    'no_border' as name,
    'checkbox' as x_type,
    1 as allow_blank,
    90 as position
FROM s_library_component
WHERE x_type = :xtype
EOD;

        $statement = $this->connection->prepare($sql);

        foreach ($components as $component) {
            $statement->execute([':xtype' => $component]);
        }
    }
}
