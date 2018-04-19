<?php

use Shopware\Framework\Migration\AbstractMigration;

class Migrations_Migration907 extends AbstractMigration
{
    /**
     * @inheritdoc
     */
    public function up($modus)
    {
        $sql = <<<'SQL'
CREATE TABLE `s_order_basket_signatures` (
  `signature` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `basket` longtext COLLATE utf8_unicode_ci NOT NULL,
  `created_at` date NOT NULL,
  PRIMARY KEY (`signature`),
  KEY (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
INSERT INTO `s_crontab` (`id`, `name`, `action`, `elementID`, `data`, `next`, `start`, `interval`, `active`, `end`, `inform_template`, `inform_mail`, `pluginID`)
VALUES (NULL, 'Basket Signature cleanup', 'CleanupSignatures', NULL, '', '2016-10-11 08:34:13', NULL, '86400', '1', '2016-10-11 08:34:13', '', '', NULL);
SQL;
        $this->addSql($sql);
    }
}
