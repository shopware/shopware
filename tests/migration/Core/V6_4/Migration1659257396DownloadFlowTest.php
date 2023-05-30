<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\LineItemProductStatesRule;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1659257396DownloadFlow;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1659257396DownloadFlow
 */
class Migration1659257396DownloadFlowTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private const RULE_NAME = 'Shopping cart / Order with digital products';
    private const FLOW_NAME = 'Deliver ordered product downloads';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->prepare();
    }

    public function testMigration(): void
    {
        $migration = new Migration1659257396DownloadFlow();
        $migration->update($this->connection);

        // Migrate twice
        $migration->update($this->connection);

        $ruleId = $this->assertRule();
        $this->assertFlow($ruleId);
        $this->assertFlowTemplate($ruleId);

        // test it can be executed multiple times
        $migration->update($this->connection);
    }

    private function assertRule(): string
    {
        $ruleCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `rule` WHERE `name` = :name',
            ['name' => self::RULE_NAME]
        );

        static::assertEquals(1, $ruleCount);

        $ruleId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `rule` WHERE `name` = :name',
            ['name' => self::RULE_NAME]
        );

        static::assertIsString($ruleId);

        $ruleConditionCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `rule_condition` WHERE `rule_id` = :id',
            ['id' => Uuid::fromHexToBytes($ruleId)]
        );

        static::assertEquals(2, $ruleConditionCount);

        $ruleLineItemConditionValue = $this->connection->fetchOne(
            'SELECT `value` FROM `rule_condition` WHERE `rule_id` = :id AND `type` = :type',
            ['id' => Uuid::fromHexToBytes($ruleId), 'type' => (new LineItemProductStatesRule())->getName()]
        );

        static::assertIsString($ruleLineItemConditionValue);

        $value = json_decode($ruleLineItemConditionValue, true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($value);
        static::assertEquals(LineItemProductStatesRule::OPERATOR_EQ, $value['operator']);
        static::assertEquals(State::IS_DOWNLOAD, $value['productState']);

        return $ruleId;
    }

    private function assertFlow(string $ruleId): void
    {
        $flowCount = (int) $this->connection->fetchOne(
            'SELECT count(*) FROM `flow` WHERE `name` = :name',
            ['name' => self::FLOW_NAME]
        );

        static::assertEquals(1, $flowCount);

        $flowId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `flow` WHERE `name` = :name',
            ['name' => self::FLOW_NAME]
        );

        static::assertIsString($flowId);

        $flowSequenceCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `flow_sequence` WHERE `flow_id` = :id',
            ['id' => Uuid::fromHexToBytes($flowId)]
        );

        static::assertEquals(3, $flowSequenceCount);

        $flowSequenceRuleId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `flow_sequence` WHERE `flow_id` = :id AND `rule_id` = :ruleId',
            ['id' => Uuid::fromHexToBytes($flowId), 'ruleId' => Uuid::fromHexToBytes($ruleId)]
        );

        static::assertIsString($flowSequenceRuleId);

        $flowSequenceActions = $this->connection->fetchFirstColumn(
            'SELECT `action_name` FROM `flow_sequence` WHERE `flow_id` = :id AND `parent_id` = :parentId',
            ['id' => Uuid::fromHexToBytes($flowId), 'parentId' => Uuid::fromHexToBytes($flowSequenceRuleId)]
        );

        static::assertIsArray($flowSequenceActions);
        static::assertContains('action.grant.download.access', $flowSequenceActions);
        static::assertContains('action.mail.send', $flowSequenceActions);
    }

    private function assertFlowTemplate(string $ruleId): void
    {
        $flowTemplateCount = (int) $this->connection->fetchOne(
            'SELECT count(*) FROM `flow_template` WHERE `name` = :name',
            ['name' => self::FLOW_NAME]
        );

        static::assertEquals(1, $flowTemplateCount);

        $config = $this->connection->fetchOne(
            'SELECT config FROM `flow_template` WHERE `name` = :name',
            ['name' => self::FLOW_NAME]
        );

        $decodedConfig = json_decode((string) $config, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decodedConfig);
        static::assertArrayHasKey('eventName', $decodedConfig);
        static::assertSame('state_enter.order_transaction.state.paid', $decodedConfig['eventName']);

        static::assertArrayHasKey('sequences', $decodedConfig);
        static::assertCount(3, $decodedConfig['sequences']);

        static::assertSame($ruleId, $decodedConfig['sequences'][0]['ruleId']);
        static::assertSame('action.grant.download.access', $decodedConfig['sequences'][1]['actionName']);
        static::assertSame('action.mail.send', $decodedConfig['sequences'][2]['actionName']);
    }

    private function prepare(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `flow_template` WHERE `name` = :name',
            ['name' => self::FLOW_NAME]
        );
        $this->connection->executeStatement(
            'DELETE FROM `flow` WHERE `name` = :name',
            ['name' => self::FLOW_NAME]
        );
        $this->connection->executeStatement(
            'DELETE FROM `rule` WHERE `name` = :name',
            ['name' => self::RULE_NAME]
        );
    }
}
