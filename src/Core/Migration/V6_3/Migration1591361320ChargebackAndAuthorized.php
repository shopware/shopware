<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\ArrayParameterType;
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
class Migration1591361320ChargebackAndAuthorized extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1591361320;
    }

    public function update(Connection $connection): void
    {
        $machineId = $this->getMachineId($connection, 'order_transaction.state');

        if (!$machineId) {
            return;
        }

        $state = [
            'technical_name' => 'authorized',
            'translations' => [
                'en-GB' => 'Authorized',
                'de-DE' => 'Autorisiert',
            ],
            'transition' => 'authorize',
            'from' => $this->getStateIds($connection, $machineId, ['open', 'in_progress', 'reminded']),
            'to' => [
                'paid' => $this->getStateId($connection, $machineId, 'paid'),
                'paid_partially' => $this->getStateId($connection, $machineId, 'paid_partially'),
                'fail' => $this->getStateId($connection, $machineId, 'failed'),
                'cancel' => $this->getStateId($connection, $machineId, 'cancelled'),
            ],
        ];

        $this->insertState($connection, $state, $machineId);

        $state = [
            'technical_name' => 'chargeback',
            'translations' => [
                'en-GB' => 'Chargeback',
                'de-DE' => 'Rückbuchung',
            ],
            'transition' => 'chargeback',
            'from' => $this->getStateIds($connection, $machineId, ['paid', 'paid_partially']),
            'to' => [
                'paid' => $this->getStateId($connection, $machineId, 'paid'),
                'paid_partially' => $this->getStateId($connection, $machineId, 'paid_partially'),
                'cancel' => $this->getStateId($connection, $machineId, 'cancelled'),
            ],
        ];

        $this->insertState($connection, $state, $machineId);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    /**
     * @param array<string, mixed> $state
     */
    private function insertState(Connection $connection, array $state, string $machineId): void
    {
        $stateId = Uuid::randomHex();

        try {
            $connection->executeStatement(
                'INSERT INTO state_machine_state (id, technical_name, state_machine_id, created_at)
             VALUES (:id, :technical_name, :state_machine_id, :created_at)',
                [
                    'id' => Uuid::fromHexToBytes($stateId),
                    'technical_name' => $state['technical_name'],
                    'state_machine_id' => Uuid::fromHexToBytes($machineId),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        } catch (UniqueConstraintViolationException) {
            // don't add states if they already exist
            return;
        }

        // import translations for current machine_state
        $languages = array_unique(array_filter([
            $this->getLanguageId('en-GB', $connection),
            Defaults::LANGUAGE_SYSTEM,
        ]));
        foreach ($languages as $language) {
            $this->insertTranslation($stateId, $state['translations']['en-GB'], $language, $connection);
        }

        $languages = array_filter([$this->getLanguageId('de-DE', $connection)]);
        foreach ($languages as $language) {
            $this->insertTranslation($stateId, $state['translations']['de-DE'], $language, $connection);
        }

        foreach ($state['from'] as $fromId) {
            if (!$fromId) {
                continue;
            }
            $this->insertTransition($state['transition'], $machineId, $fromId, $stateId, $connection);
        }

        foreach ($state['to'] as $action => $toId) {
            if (!$toId) {
                continue;
            }
            $this->insertTransition($action, $machineId, $stateId, $toId, $connection);
        }
    }

    private function insertTransition(string $action, string $machineId, string $from, string $to, Connection $connection): void
    {
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

    private function insertTranslation(string $stateId, string $name, string $languageId, Connection $connection): void
    {
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

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private function getStateIds(Connection $connection, string $machineId, array $names): array
    {
        return $connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) as id FROM state_machine_state WHERE technical_name IN (:name) AND state_machine_id = :id',
            ['name' => $names, 'id' => Uuid::fromHexToBytes($machineId)],
            ['name' => ArrayParameterType::STRING]
        );
    }
}
