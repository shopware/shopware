<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\V6_6\Migration1739198249FixOrderDeliveryStateMachineName;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1739198249FixOrderDeliveryStateMachineName::class)]
class Migration1739198249FixOrderDeliveryStateMachineNameTest extends TestCase
{
    use ImportTranslationsTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testStateMachineName(): void
    {
        $this->executeMigration();

        $germanIds = $this->getLanguageIds($this->connection, 'de-DE');
        $englishIds = array_unique(array_diff(
            array_merge($this->getLanguageIds($this->connection, 'en-GB'), [Defaults::LANGUAGE_SYSTEM]),
            $germanIds
        ));

        $stateMachineId = $this->connection->fetchOne('SELECT id FROM state_machine WHERE technical_name = :technicalName', ['technicalName' => OrderDeliveryStates::STATE_MACHINE]);
        if (!\is_string($stateMachineId) || empty($stateMachineId)) {
            return;
        }

        $germanNames = $this->connection->fetchFirstColumn(
            'SELECT name FROM state_machine_translation WHERE state_machine_id = :stateMachineId AND language_id IN (:languageIds) AND updated_at IS NULL',
            [
                'stateMachineId' => $stateMachineId,
                'languageIds' => Uuid::fromHexToBytesList($germanIds),
            ],
            [
                'stateMachineId' => ParameterType::BINARY,
                'languageIds' => ArrayParameterType::BINARY,
            ]
        );

        foreach ($germanNames as $germanName) {
            static::assertEquals('Versandstatus', $germanName);
        }

        $englishNames = $this->connection->fetchFirstColumn(
            'SELECT name FROM state_machine_translation WHERE state_machine_id = :stateMachineId AND language_id IN (:languageIds) AND updated_at IS NULL',
            [
                'stateMachineId' => $stateMachineId,
                'languageIds' => Uuid::fromHexToBytesList($englishIds),
            ],
            [
                'stateMachineId' => ParameterType::BINARY,
                'languageIds' => ArrayParameterType::BINARY,
            ]
        );

        foreach ($englishNames as $englishName) {
            static::assertEquals('Delivery state', $englishName);
        }
    }

    private function executeMigration(): void
    {
        $migration = new Migration1739198249FixOrderDeliveryStateMachineName();
        static::assertEquals($migration->getCreationTimestamp(), 1739198249);

        $this->rollback();

        $migration->update($this->connection);
        $migration->update($this->connection);
    }

    private function rollback(): void
    {
        $germanIds = $this->getLanguageIds($this->connection, 'de-DE');
        $englishIds = array_unique(array_diff(
            array_merge($this->getLanguageIds($this->connection, 'en-GB'), [Defaults::LANGUAGE_SYSTEM]),
            $germanIds
        ));

        $stateMachineId = $this->connection->fetchOne('SELECT id FROM state_machine WHERE technical_name = :technicalName', ['technicalName' => OrderDeliveryStates::STATE_MACHINE]);
        if (!\is_string($stateMachineId) || empty($stateMachineId)) {
            return;
        }

        if (!empty($germanIds)) {
            $this->connection->executeStatement('UPDATE state_machine_translation SET name = :name WHERE state_machine_id = :stateMachineId AND language_id IN (:languageIds) AND updated_at IS NULL', [
                'name' => 'Bestellstatus',
                'stateMachineId' => $stateMachineId,
                'languageIds' => Uuid::fromHexToBytesList($germanIds),
            ], [
                'name' => ParameterType::STRING,
                'stateMachineId' => ParameterType::BINARY,
                'languageIds' => ArrayParameterType::BINARY,
            ]);
        }

        if (!empty($englishIds)) {
            $this->connection->executeStatement('UPDATE state_machine_translation SET name = :name WHERE state_machine_id = :stateMachineId AND language_id IN (:languageIds) AND updated_at IS NULL', [
                'name' => 'Order state',
                'stateMachineId' => $stateMachineId,
                'languageIds' => Uuid::fromHexToBytesList($englishIds),
            ], [
                'name' => ParameterType::STRING,
                'stateMachineId' => ParameterType::BINARY,
                'languageIds' => ArrayParameterType::BINARY,
            ]);
        }
    }
}
