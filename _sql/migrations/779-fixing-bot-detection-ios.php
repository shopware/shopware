<?php

class Migrations_Migration779 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->fixStandardBlacklist();

        $this->fixCustomerBlacklists();
    }

    private function fixStandardBlacklist()
    {
        $blacklistConfig = $this->connection
            ->query("SELECT * FROM s_core_config_elements WHERE name = 'botBlackList'")
            ->fetchAll(PDO::FETCH_ASSOC)
        ;

        if (empty($blacklistConfig)) {
            return;
        }

        $botList = $this->getFilteredBotList($blacklistConfig[0]['value']);
        $statement = $this->connection->prepare("
            UPDATE s_core_config_elements
            SET value = :value 
            WHERE id = :id
        ");
        $statement->execute([
            'value' => $botList,
            'id' => $blacklistConfig[0]['id']
        ]);
    }

    private function fixCustomerBlacklists()
    {
        $blacklistConfig = $this->connection
            ->query("SELECT v.* FROM s_core_config_values as v INNER JOIN s_core_config_elements as e on v.element_id = e.id WHERE e.name = 'botBlackList'")
            ->fetchAll(PDO::FETCH_ASSOC)
        ;

        if (empty($blacklistConfig)) {
            return;
        }

        foreach ($blacklistConfig as $config) {
            $botList = $this->getFilteredBotList($config['value']);
            $statement = $this->connection->prepare("
                UPDATE s_core_config_values
                SET value = :value 
                WHERE id = :id
            ");
            $statement->execute([
                'value' => $botList,
                'id' => $config['id']
            ]);
        }
    }

    /**
     * @param string $botConfiguration
     * @return string
     */
    private function getFilteredBotList($botConfiguration)
    {
        $botList = explode(';', unserialize($botConfiguration));
        $botList = array_filter($botList, function ($bot) {
            return $bot !== 'legs';
        });

        return serialize(implode(";", $botList));
    }
}
