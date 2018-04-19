<?php
class Migrations_Migration353 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
	    // Add template
	    $sql = <<<'EOD'
INSERT INTO `s_emotion_templates` (`name`, `file`) VALUES
('Horizontales Scrolling', 'horizontal_scrolling.tpl');
INSERT INTO `s_emotion_grid` (`name`, `cols`, `rows`, `cell_height`, `article_height`, `gutter`) VALUES
('Horizontales Scrolling', 40, 8, 185, 2, 10);
EOD;
        $this->addSql($sql);
    }
}
