<?php

class Migrations_Migration495 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
            UPDATE s_emotion e
            SET e.rows = (SELECT eg.rows FROM s_emotion_grid eg WHERE e.grid_id = eg.id)
            WHERE e.rows = 0
            AND e.grid_id IS NOT NULL
EOD;
        $this->addSql($sql);
    }
}