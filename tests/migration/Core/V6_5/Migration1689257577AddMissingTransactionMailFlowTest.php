<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_5\Migration1689257577AddMissingTransactionMailFlow;

/**
 * @internal
 */
#[CoversClass(Migration1689257577AddMissingTransactionMailFlow::class)]
class Migration1689257577AddMissingTransactionMailFlowTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private const AUTHORIZED_NAME = Migration1689257577AddMissingTransactionMailFlow::AUTHORIZED_FLOW;

    private const CHARGEBACK_NAME = Migration1689257577AddMissingTransactionMailFlow::CHARGEBACK_FLOW;

    private const UNCONFIRMED_NAME = Migration1689257577AddMissingTransactionMailFlow::UNCONFIRMED_FLOW;

    private Connection $connection;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement('DELETE FROM `flow_template` WHERE `name` IN (?, ?, ?)', [
            self::AUTHORIZED_NAME,
            self::CHARGEBACK_NAME,
            self::UNCONFIRMED_NAME,
        ]);

        $this->connection->executeStatement('DELETE FROM `flow` WHERE `name` IN (?, ?, ?)', [
            self::AUTHORIZED_NAME,
            self::CHARGEBACK_NAME,
            self::UNCONFIRMED_NAME,
        ]);
    }

    /**
     * @throws Exception
     * @throws \JsonException
     */
    public function testMigration(): void
    {
        $migration = new Migration1689257577AddMissingTransactionMailFlow();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $this->assertFlows();
        $this->assertFlowTemplates();
    }

    public function assertFlows(): void
    {
        $assertFlow = function (string $flowId): void {
            $flowCount = $this->connection->fetchOne('SELECT COUNT(*) FROM `flow` WHERE `id` = :id', ['id' => Uuid::fromHexToBytes($flowId)]);
            static::assertEquals('1', $flowCount);

            $flowSequenceCount = $this->connection->fetchOne('SELECT COUNT(*) FROM `flow_sequence` WHERE `flow_id` = :id', ['id' => Uuid::fromHexToBytes($flowId)]);
            static::assertEquals('1', $flowSequenceCount);

            $flowSequenceActions = $this->connection->fetchFirstColumn('SELECT `action_name` FROM `flow_sequence` WHERE `flow_id` = :id', ['id' => Uuid::fromHexToBytes($flowId)]);
            static::assertCount(1, $flowSequenceActions);
            static::assertContains('action.mail.send', $flowSequenceActions);
        };

        $flowIds = $this->getFlowIds();

        foreach ($flowIds as $flowId) {
            static::assertIsString($flowId);

            $assertFlow($flowId);
        }
    }

    public function assertFlowTemplates(): void
    {
        $assertFlowTemplate = function (string $flowName): void {
            $flowTemplateCount = $this->connection->fetchOne('SELECT count(*) FROM `flow_template` WHERE `name` = :name', ['name' => $flowName]);
            static::assertEquals('1', $flowTemplateCount);

            $config = $this->connection->fetchOne('SELECT config FROM `flow_template` WHERE `name` = :name', ['name' => $flowName]);
            static::assertIsString($config);

            $decodedConfig = json_decode($config, true, 512, \JSON_THROW_ON_ERROR);
            static::assertIsArray($decodedConfig);
            static::assertArrayHasKey('sequences', $decodedConfig);
            static::assertCount(1, $decodedConfig['sequences']);
        };

        $assertFlowTemplate(self::AUTHORIZED_NAME);
        $assertFlowTemplate(self::CHARGEBACK_NAME);
        $assertFlowTemplate(self::UNCONFIRMED_NAME);
    }

    /**
     * @throws Exception
     *
     * @return array<string, string|bool>
     */
    public function getFlowIds(): array
    {
        $getFlowIds = function (string $flowName): string|bool {
            return $this->connection->fetchOne('SELECT LOWER(HEX(`id`)) FROM `flow` WHERE `name` = :name', ['name' => $flowName]);
        };

        return [
            self::AUTHORIZED_NAME => $getFlowIds(self::AUTHORIZED_NAME),
            self::CHARGEBACK_NAME => $getFlowIds(self::CHARGEBACK_NAME),
            self::UNCONFIRMED_NAME => $getFlowIds(self::UNCONFIRMED_NAME),
        ];
    }
}
