<?php

class Migrations_Migration609 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addAlbum();
        $this->createCronJob();
    }

    private function createCronJob()
    {
        $sql = <<<SQL
            INSERT INTO s_crontab (`name`, `action`, `next`, `start`, `interval`, `active`, `end`, `pluginID`)
            VALUES ('Media Garbage Collector', 'MediaCrawler', now(), NULL, 86400, 0, now(), NULL)
SQL;

        $this->addSql($sql);
    }

    private function addAlbum()
    {
        $sql = <<<SQL
            INSERT INTO `s_media_album` (`id`, `name`, `parentID`, `position`) VALUES
            (-13, 'Papierkorb', NULL, 12);
SQL;

        $this->addSql($sql);
    }
}
