<?php

class Migrations_Migration603 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->createMenuEntry();
        $this->createProductStreamTable();
        $this->createProductStreamForeignKey();
        $this->createEmotionComponent();
    }

    /**
     * @return string
     */
    private function createMenuEntry()
    {
        $sql = <<<'EOD'
INSERT INTO `s_core_menu` (`id`, `parent`, `hyperlink`, `name`, `onclick`, `style`, `class`, `position`, `active`, `pluginID`, `resourceID`, `controller`, `shortcut`, `action`)
VALUES (NULL, '1', '', 'Product Streams', '', NULL, '', '50', '1', NULL, NULL, 'ProductStream', '', 'index');
EOD;
        $this->addSql($sql);

        return $sql;
    }

    /**
     * @return string
     */
    private function createProductStreamTable()
    {
        $sql = <<<'EOD'
CREATE TABLE IF NOT EXISTS `s_product_streams` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `conditions` text COLLATE utf8_unicode_ci,
    `type` int(11) COLLATE utf8_unicode_ci,
    `sorting` text COLLATE utf8_unicode_ci,
    `description` text COLLATE utf8_unicode_ci,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
CREATE TABLE IF NOT EXISTS `s_product_streams_articles` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `stream_id` int(11) unsigned NOT NULL,
    `article_id` int(11) unsigned NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `stream_id` (`stream_id`,`article_id`),
    CONSTRAINT s_product_streams_articles_fk_stream_id FOREIGN KEY (stream_id) REFERENCES s_product_streams (id) ON DELETE CASCADE,
    CONSTRAINT s_product_streams_articles_fk_article_id FOREIGN KEY (article_id) REFERENCES s_articles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
CREATE TABLE IF NOT EXISTS `s_product_streams_selection` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `stream_id` int(11) unsigned NOT NULL,
    `article_id` int(11) unsigned NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `stream_id` (`stream_id`,`article_id`),
    CONSTRAINT s_product_streams_selection_fk_stream_id FOREIGN KEY (stream_id) REFERENCES s_product_streams (id) ON DELETE CASCADE,
    CONSTRAINT s_product_streams_selection_fk_article_id FOREIGN KEY (article_id) REFERENCES s_articles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;

        $this->addSql($sql);


        return $sql;
    }

    /**
     * @return string
     */
    private function createProductStreamForeignKey()
    {
        $sql = <<<'EOD'
ALTER TABLE `s_categories`
ADD `stream_id` int(11) unsigned NULL DEFAULT NULL,
ADD INDEX `stream_id` (`stream_id`),
ADD CONSTRAINT s_categories_fk_stream_id FOREIGN KEY (stream_id) REFERENCES s_product_streams (id) ON DELETE SET NULL;
EOD;
        $this->addSql($sql);

        return $sql;
    }

    private function createEmotionComponent()
    {
        $sql = <<<'EOD'
INSERT INTO `s_library_component_field` (`id`, `componentID`, `name`, `x_type`, `value_type`, `field_label`, `support_text`, `help_title`, `help_text`, `store`, `display_field`, `value_field`, `default_value`, `allow_blank`, `position`)
VALUES (NULL, '11', 'article_slider_stream', 'emotion-components-fields-product-stream-selection', '', '', '', '', '', '', '', '', '', '0', '38');
EOD;
        $this->addSql($sql);
    }
}
