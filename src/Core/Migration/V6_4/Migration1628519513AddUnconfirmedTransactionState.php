<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1628519513AddUnconfirmedTransactionState extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1628519513;
    }

    public function update(Connection $connection): void
    {
        $machineId = $this->getMachineId($connection, 'order_transaction.state');

        if (!$machineId) {
            return;
        }

        $stateId = Uuid::randomHex();

        try {
            $connection->executeStatement(
                'INSERT INTO state_machine_state (id, technical_name, state_machine_id, created_at)
             VALUES (:id, :technical_name, :state_machine_id, :created_at)',
                [
                    'id' => Uuid::fromHexToBytes($stateId),
                    'technical_name' => 'unconfirmed',
                    'state_machine_id' => Uuid::fromHexToBytes($machineId),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        } catch (UniqueConstraintViolationException) {
            // don't add state if it already exists
            return;
        }

        $this->insertTranslations($stateId, $connection);

        // from
        $this->insertTransition('process_unconfirmed', $machineId, $this->getStateId($connection, $machineId, 'open'), $stateId, $connection);
        $this->insertTransition('process_unconfirmed', $machineId, $this->getStateId($connection, $machineId, 'reminded'), $stateId, $connection);
        $this->insertTransition('process_unconfirmed', $machineId, $this->getStateId($connection, $machineId, 'failed'), $stateId, $connection);
        $this->insertTransition('process_unconfirmed', $machineId, $this->getStateId($connection, $machineId, 'cancelled'), $stateId, $connection);
        $this->insertTransition('process_unconfirmed', $machineId, $this->getStateId($connection, $machineId, 'paid_partially'), $stateId, $connection);

        // to
        $this->insertTransition('paid', $machineId, $stateId, $this->getStateId($connection, $machineId, 'paid'), $connection);
        $this->insertTransition('paid_partially', $machineId, $stateId, $this->getStateId($connection, $machineId, 'paid_partially'), $connection);
        $this->insertTransition('fail', $machineId, $stateId, $this->getStateId($connection, $machineId, 'failed'), $connection);
        $this->insertTransition('cancel', $machineId, $stateId, $this->getStateId($connection, $machineId, 'cancelled'), $connection);
        $this->insertTransition('authorize', $machineId, $stateId, $this->getStateId($connection, $machineId, 'authorized'), $connection);
        $this->insertTransition('reopen', $machineId, $stateId, $this->getStateId($connection, $machineId, 'open'), $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function insertTransition(string $action, string $machineId, ?string $from, ?string $to, Connection $connection): void
    {
        if ($from === null || $to === null) {
            return;
        }

        $connection->executeStatement(
            'REPLACE INTO state_machine_transition (id, action_name, state_machine_id, from_state_id, to_state_id, created_at)
            VALUES (:id, :action_name, :state_machine_id, :from_state_id, :to_state_id, :created_at)',
            [
                'id' => Uuid::randomBytes(),
                'action_name' => $action,
                'state_machine_id' => Uuid::fromHexToBytes($machineId),
                'from_state_id' => Uuid::fromHexToBytes($from),
                'to_state_id' => Uuid::fromHexToBytes($to),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    private function insertTranslations(string $stateId, Connection $connection): void
    {
        $languages = [Defaults::LANGUAGE_SYSTEM => 'Unconfirmed'];
        if (($enGbId = $this->getLanguageId('en-GB', $connection)) !== null) {
            $languages[$enGbId] = 'Unconfirmed';
        }
        if (($deDeId = $this->getLanguageId('de-DE', $connection)) !== null) {
            $languages[$deDeId] = 'Unbestätigt';
        }

        foreach ($languages as $languageId => $name) {
            $connection->executeStatement(
                'REPLACE INTO state_machine_state_translation
                 (`language_id`, `state_machine_state_id`, `name`, `created_at`)
                 VALUES
                 (:language_id, :state_machine_state_id, :name, :created_at)',
                [
                    'language_id' => Uuid::fromHexToBytes($languageId),
                    'state_machine_state_id' => Uuid::fromHexToBytes($stateId),
                    'name' => $name,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }
    }

    private function getLanguageId(string $locale, Connection $connection): ?string
    {
        $column = $connection->fetchOne('
            SELECT LOWER(HEX(`language`.id))
            FROM `language`
            INNER JOIN locale
                ON locale.id = `language`.translation_code_id
                AND locale.code = :locale
        ', ['locale' => $locale]);

        return $column === false ? null : ((string) $column);
    }

    private function getMachineId(Connection $connection, string $name): string
    {
        return $connection->fetchOne(
            'SELECT LOWER(HEX(id)) FROM state_machine WHERE technical_name = :name',
            ['name' => $name]
        );
    }

    private function getStateId(Connection $connection, string $machineId, string $name): string
    {
        return $connection->fetchOne(
            'SELECT LOWER(HEX(id)) as id FROM state_machine_state WHERE technical_name = :name AND state_machine_id = :id',
            ['name' => $name, 'id' => Uuid::fromHexToBytes($machineId)]
        );
    }
}
