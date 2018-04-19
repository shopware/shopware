<?php
class Migrations_Migration140 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        UPDATE `s_emotion` SET grid_id =
            (SELECT id FROM s_emotion_grid LIMIT 1)
        WHERE grid_id IS NULL OR grid_id = 0;
EOD;
        $this->addSql($sql);
    }
}



