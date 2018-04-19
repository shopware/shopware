<?php

class Migrations_Migration739 extends Shopware\Framework\Migration\AbstractMigration
{
    /**
     * @param string $modus
     * @return void
     */
    public function up($modus)
    {
        $viewports = [ 'xs', 's', 'm', 'l', 'xl' ];

        $sql = <<<EOD
INSERT IGNORE INTO s_emotion_element_viewports (elementID, emotionID, alias, start_row, start_col, end_row, end_col, visible)
SELECT
    id as elementID,
    emotionID,
    :viewport,
    start_row,
    start_col,
    end_row,
    end_col,
    1 as visible
FROM s_emotion_element
EOD;

        $statement = $this->connection->prepare($sql);

        foreach ($viewports as $viewport) {
            $statement->execute([':viewport' => $viewport]);
        }
    }
}
