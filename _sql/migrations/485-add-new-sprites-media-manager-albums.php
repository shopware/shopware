<?php

class Migrations_Migration485 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->updateAlbumIcon(-12, 'sprite-hard-hat');
        $this->updateAlbumIcon(-11, 'sprite-leaf');
        $this->updateAlbumIcon(-5, 'sprite-inbox-document-text');
        $this->updateAlbumIcon(-4, 'sprite-target');
        $this->updateAlbumIcon(-3, 'sprite-target');
        $this->updateAlbumIcon(-2, 'sprite-pictures');
        $this->updateAlbumIcon(-1, 'sprite-inbox');
    }

    private function updateAlbumIcon($albumId, $icon)
    {
        $sql = <<< SQL
            UPDATE s_media_album_settings
            SET icon = '$icon'
            WHERE albumID = $albumId
            AND icon = 'sprite-blue-folder';
SQL;
        $this->addSql($sql);
    }
}