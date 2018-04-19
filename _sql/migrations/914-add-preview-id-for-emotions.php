<?php

use Shopware\Framework\Migration\AbstractMigration;

class Migrations_Migration914 extends AbstractMigration
{
    /**
     * @inheritdoc
     */
    public function up($modus)
    {
        $sql = <<<SQL
ALTER TABLE `s_emotion`
  ADD `preview_id` int(11) NULL,
  ADD `preview_secret` varchar(32) NULL,
  ADD UNIQUE `preview_id` (`preview_id`);
SQL;

        $this->addSql($sql);
    }
}
