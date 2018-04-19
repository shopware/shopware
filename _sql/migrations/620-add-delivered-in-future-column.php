<?php

class Migrations_Migration620 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $statement = $this->getConnection()->prepare('SHOW COLUMNS FROM `s_campaigns_mailings`;');
        $statement->execute();
        $result = $statement->fetchAll(\PDO::FETCH_COLUMN);

        if (!in_array('timed_delivery', $result)) {
            $this->addTimedDeliveryColumn();
        }
    }

    private function addTimedDeliveryColumn()
    {
        $sql = <<<EOD
            ALTER TABLE `s_campaigns_mailings` ADD COLUMN `timed_delivery` DATETIME DEFAULT NULL;
EOD;
        $this->addSql($sql);
    }
}
