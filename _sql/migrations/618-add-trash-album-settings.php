<?php

class Migrations_Migration618 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
INSERT INTO `s_media_album_settings` (`albumID`, `create_thumbnails`, `thumbnail_size`, `icon`, `thumbnail_high_dpi`, `thumbnail_quality`, `thumbnail_high_dpi_quality`) VALUES
(-13, 0, '', 'sprite-bin-metal-full', 0, 90, 60) ON DUPLICATE KEY UPDATE `icon` = 'sprite-bin-metal-full';
EOD;

        $this->addSql($sql);
    }
}
