<?php

class Migrations_Migration372 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->createTables();
        $this->addAcl();
        $this->addSampleData();
    }

    private function createTables()
    {
        $sql = <<<'EOD'
            CREATE TABLE IF NOT EXISTS `s_multi_edit_filter`  (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `name` varchar(255) NOT NULL COMMENT 'Name of the filter',
              `filter_string` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'The actual filter string',
              `description` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'User description of the filter',
              `created` datetime DEFAULT 0 COMMENT 'Creation date',
              `is_favorite` tinyint(1) DEFAULT 0 NOT NULL COMMENT 'Did the user mark this filter as favorite?',
              `is_simple` tinyint(1) DEFAULT 0 NOT NULL COMMENT 'Can the filter be loaded and modified with the simple editor?',
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1 COMMENT 'Holds all multi edit filters';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
            CREATE TABLE IF NOT EXISTS `s_multi_edit_backup`  (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `filter_string` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Filter string of the backed up change',
              `operation_string` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Operations applied after the backup',
              `items` int(255) unsigned NOT NULL COMMENT 'Number of items affected by the backup',
              `date` datetime DEFAULT 0 COMMENT 'Creation date',
		      `size` int(255) unsigned NOT NULL COMMENT 'Size of the backup file',
              `path` varchar(255) NOT NULL COMMENT 'Path of the backup file',
			  `hash` varchar(255) NOT NULL COMMENT 'Hash of the backup file',
              PRIMARY KEY (`id`),
              KEY (`date`),
              KEY (`size`),
              KEY (`items`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1 COMMENT 'Backups known to the system';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
          CREATE TABLE IF NOT EXISTS `s_multi_edit_queue`  (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `resource` varchar(255) NOT NULL COMMENT 'Queued resource (e.g. product)',
              `filter_string` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'The actual filter string',
              `operations` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Operations to apply',
              `items` int(255) unsigned NOT NULL COMMENT 'Initial number of objects in the queue',
              `active` tinyint(1) DEFAULT 0 NOT NULL COMMENT 'When active, the queue is allowed to be progressed by cronjob',
              `created` datetime DEFAULT 0 COMMENT 'Creation date',
              PRIMARY KEY (`id`),
              KEY (`filter_string`(255)),
              KEY (`created`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1 COMMENT 'Holds the batch process queue';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
            CREATE TABLE IF NOT EXISTS `s_multi_edit_queue_articles`  (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `queue_id` int(11) unsigned NOT NULL COMMENT 'Id of the queue this article belongs to',
              `detail_id` int(11) unsigned NOT NULL COMMENT 'Id of the article detail',
              PRIMARY KEY (`id`),
              KEY (`detail_id`),
              KEY (`queue_id`),
              UNIQUE (`queue_id`, `detail_id`),
              CONSTRAINT `s_multi_edit_queue_articles_ibfk_1` FOREIGN KEY (`detail_id`) REFERENCES `s_articles_details` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
              CONSTRAINT `s_multi_edit_queue_articles_ibfk_2` FOREIGN KEY (`queue_id`) REFERENCES `s_multi_edit_queue` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1 COMMENT 'Products belonging to a certain queue';
EOD;
        $this->addSql($sql);
    }

    /**
     * Remove existing acl resources (multi edit, article list) and add new
     */
    private function addAcl()
    {
        $sql = <<<'EOD'
            SET @resourceId = (SELECT id FROM s_core_acl_resources WHERE name = 'swagmultiedit');
            DELETE FROM s_core_acl_roles WHERE resourceID = @resourceId;
            DELETE FROM s_core_acl_privileges WHERE resourceID = @resourceId;
            DELETE FROM s_core_acl_resources WHERE name = 'swagmultiedit';

            INSERT IGNORE INTO s_core_acl_resources (name) VALUES ('articlelist');

            SET @resourceId = (SELECT id FROM s_core_acl_resources WHERE name = 'articlelist');

            INSERT IGNORE INTO s_core_acl_privileges (resourceID,name) VALUES (@resourceId, 'read');
            INSERT IGNORE INTO s_core_acl_privileges (resourceID,name) VALUES (@resourceId, 'createFilters');
            INSERT IGNORE INTO s_core_acl_privileges (resourceID,name) VALUES (@resourceId, 'editFilters');
            INSERT IGNORE INTO s_core_acl_privileges (resourceID,name) VALUES (@resourceId, 'deleteFilters');
            INSERT IGNORE INTO s_core_acl_privileges (resourceID,name) VALUES (@resourceId, 'editSingleArticle');
            INSERT IGNORE INTO s_core_acl_privileges (resourceID,name) VALUES (@resourceId, 'doMultiEdit');
            INSERT IGNORE INTO s_core_acl_privileges (resourceID,name) VALUES (@resourceId, 'doBackup');
            UPDATE s_core_menu SET resourceID = @resourceId WHERE controller = 'ArticleList';
EOD;
        $this->addSql($sql);
    }

    /**
     * Insert the demo data
     */
    private function addSampleData()
    {
        $sql = <<<'EOD'
INSERT IGNORE

INTO
    `s_multi_edit_filter`
VALUES
    (1,'<b>Abverkauf</b><br><small>nicht auf Lager</small>','   ARTICLE.LASTSTOCK  ISTRUE and DETAIL.INSTOCK <= 0','Abverkauf-Artikel ohne Lagerbestand',NULL,1,0),
    (2,'Hauptartikel','ismain','Alle Hauptartikel (einfache Artikel und Standardvarianten)',NULL,0,0),
    (3,'Mit Staffelpreisen','HASBLOCKPRICE','',NULL,0,0),
    (4,'Highlight','ARTICLE.HIGHLIGHT ISTRUE ','Zeit alle Highlight-Artikel',NULL,0,0),
    (5,'Konfigurator-Artikel','HASCONFIGURATOR  AND ISMAIN ','Artikel mit Konfiguratoren',NULL,0,0),
    (7,'Varianten','HASCONFIGURATOR ','Alle Varianten',NULL,0,0),
    (8,'Ohne Kategorie','CATEGORY.ID ISNULL  and ISMAIN ','Artikel ohne Kategoriezuordnung',NULL,1,0),
    (16,'Artikel ohne Bilder','HASNOIMAGE ','Artikel ohne Bilder',NULL,1,0),
    (17,'Komplexer Filter','ismain and CATEGORY.ACTIVE ISTRUE and SUPPLIER.NAME IN ( \"Teapavilion\" , \"Feinbrennerei Sasse\" ) ','',NULL,0,0),
    (18,'Artikel mit Händlerpreisen','PRICE.CUSTOMERGROUPKEY IN (\"B2B\" , \"H\")','Alle Artikel, für die Händlerpreise gepflegt werden.',NULL,0,0),
    (20,'Rote Artikel','CONFIGURATOROPTION.NAME = \"%Rot%\"  or PROPERTYOPTION.VALUE = \"rot\" ','Alle Artikel mit \"rot\" als Konfiguratoroption oder Eigenschaft',NULL,0,0),
    (21,'Regulärer Ausdruck','DETAIL.NUMBER !~ \"^sw[0-9]*\" ','Findet alle Artikel, die <b>nicht</b> eine Bestellnummer nach dem Schema swZAHL haben.',NULL,0,0),
    (22,'Artikel ohne Bewertung','  VOTE.ID ISNULL  and ismain','Zeigt alle Artikel ohne Bewertungen und Kommentar',NULL,0,0),
    (23,'Artikel mit nicht-freigeschalteten Bewertungen','VOTE.ACTIVE = \"0\"','Zeigt alle Artikel, die mindestens eine inaktive Bewertung haben',NULL, 0,1);

EOD;

        $this->addSql($sql);
    }
}
