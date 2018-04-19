<?php

class Migrations_Migration370 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $statement = $this->connection->prepare("SELECT * FROM s_core_config_elements WHERE name = 'seoqueryalias'");
        $statement->execute();
        $config = $statement->fetch(PDO::FETCH_ASSOC);

        if (!empty($config)) {
            $value = unserialize($config['value']);
            if (strpos($value, 'sSort') === false) {
                $value .= ',
priceMin=min,
priceMax=max,
shippingFree=free,
immediateDelivery=delivery,
sSort=o';

                $statement = $this->connection->prepare("UPDATE s_core_config_elements SET value = ? WHERE id = ?");
                $statement->execute(array(serialize($value), $config['id']));
            }

            $statement = $this->connection->prepare("SELECT * FROM s_core_config_values WHERE element_id = ?");
            $statement->execute(array($config['id']));
            $values = $statement->fetchAll(PDO::FETCH_ASSOC);

            foreach($values as $shopValue) {
                if (empty($shopValue) || empty($shopValue['value'])) {
                    continue;
                }

                $value = unserialize($shopValue['value']);
                if (strpos($value, 'sSort') !== false) {
                    continue;
                }

                $value .= ',
priceMin=min,
priceMax=max,
shippingFree=free,
immediateDelivery=delivery,
sSort=o';

                $statement = $this->connection->prepare("UPDATE s_core_config_values SET value = ? WHERE id = ?");
                $statement->execute(array(serialize($value), $shopValue['id']));

            }

        }

        $sql = <<<'EOD'
ALTER TABLE `s_articles_vote`ADD INDEX `vote_average` (`points`);
ALTER TABLE `s_articles_prices` ADD INDEX `product_prices` (`articledetailsID`, `from`);
EOD;
        $this->addSql($sql);
    }

}
