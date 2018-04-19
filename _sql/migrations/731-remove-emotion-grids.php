<?php

class Migrations_Migration731 extends Shopware\Framework\Migration\AbstractMigration
{
    /**
     * @param string $modus
     * @return void
     */
    public function up($modus)
    {
        $this->addSql("ALTER TABLE `s_emotion` ADD `cell_spacing` INT NOT NULL AFTER `cols`");

        $sql = <<<'EOD'
UPDATE `s_emotion` AS e
INNER JOIN s_emotion_grid AS eg
ON e.grid_id = eg.id SET
e.cols = eg.cols,
e.rows = eg.rows,
e.cell_spacing = eg.gutter,
e.cell_height = eg.cell_height,
e.article_height = eg.article_height
EOD;

        $this->addSql($sql);

        $this->addSql("ALTER TABLE `s_emotion` DROP `grid_id`");

        $this->addSql("DROP TABLE IF EXISTS s_emotion_grid");
    }
}
