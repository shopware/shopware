<?php

class Migrations_Migration733 extends Shopware\Framework\Migration\AbstractMigration
{
    /**
     * @param string $modus
     * @return void
     */
    public function up($modus)
    {
        $translatables = [
            ['name' => 'article_slider_title',      'componentID' => 11],
            ['name' => 'title',                     'componentID' => 3],
            ['name' => 'link',                      'componentID' => 3],
            ['name' => 'banner_slider_title',       'componentID' => 7],
            ['name' => 'javascript',                'componentID' => 13],
            ['name' => 'smarty',                    'componentID' => 13],
            ['name' => 'manufacturer_slider_title', 'componentID' => 10],
            ['name' => 'iframe_url',                'componentID' => 9],
            ['name' => 'cms_title',                 'componentID' => 2],
            ['name' => 'text',                      'componentID' => 2],
            ['name' => 'video_id',                  'componentID' => 8],
        ];

        $sql = "ALTER TABLE `s_library_component_field` ADD translatable INT(1) NOT NULL DEFAULT 0 AFTER `allow_blank`";
        $statement = $this->connection->prepare($sql);
        $statement->execute();

        $sql = <<<EOD
UPDATE `s_library_component_field`
    SET `translatable` = 1
    WHERE `name` = :name
    AND `componentID` = :componentID
EOD;
        $statement = $this->connection->prepare($sql);

        foreach ($translatables as $translatable) {
            $statement->execute([
                ':name' => $translatable['name'],
                ':componentID' => $translatable['componentID'],
            ]);
        }
    }
}
