<?php

class Migrations_Migration737 extends Shopware\Framework\Migration\AbstractMigration
{
    /**
     * @param string $modus
     * @return void
     */
    public function up($modus)
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `s_emotion_element_viewports` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `elementID` INT(11) NOT NULL,
    `emotionID` INT(11) NOT NULL,
    `alias` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `start_row` INT(11) NOT NULL,
    `start_col` INT(11) NOT NULL,
    `end_row` INT(11) NOT NULL,
    `end_col` INT(11) NOT NULL,
    `visible` INT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`)) ENGINE = InnoDB
    DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $this->addSql($sql);
    }
}
