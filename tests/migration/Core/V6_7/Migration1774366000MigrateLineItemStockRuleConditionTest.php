<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1774366000MigrateLineItemStockRuleCondition;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1774366000MigrateLineItemStockRuleCondition::class)]
class Migration1774366000MigrateLineItemStockRuleConditionTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    private string $oldRuleId;

    private string $oldConditionId;

    private string $otherRuleId;

    private string $otherConditionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);

        $this->oldRuleId = Uuid::randomBytes();
        $this->oldConditionId = Uuid::randomBytes();
        $this->otherRuleId = Uuid::randomBytes();
        $this->otherConditionId = Uuid::randomBytes();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1774366000, (new Migration1774366000MigrateLineItemStockRuleCondition())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->createTestRulesAndConditions();

        $migration = new Migration1774366000MigrateLineItemStockRuleCondition();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $oldCondition = $this->connection->fetchAssociative(
            'SELECT `type`, `value` FROM `rule_condition` WHERE `id` = :id',
            ['id' => $this->oldConditionId]
        );

        static::assertIsArray($oldCondition);
        static::assertSame('cartLineItemActualStock', $oldCondition['type']);
        static::assertEquals(
            [
                'operator' => '>=',
                'stock' => 10,
            ],
            json_decode((string) $oldCondition['value'], true, 512, \JSON_THROW_ON_ERROR)
        );

        $checkPayloadSql = 'SELECT `payload` FROM `rule` WHERE `id` = :id';
        static::assertNull($this->connection->fetchOne($checkPayloadSql, ['id' => $this->oldRuleId]));
        static::assertSame('{"keep":true}', $this->connection->fetchOne($checkPayloadSql, ['id' => $this->otherRuleId]));

        $indexers = (new IndexerQueuer($this->connection))->getIndexers();
        static::assertArrayHasKey('rule.indexer', $indexers);
    }

    private function createTestRulesAndConditions(): void
    {
        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('rule', [
            'id' => $this->oldRuleId,
            'name' => 'old line item stock rule',
            'priority' => 1,
            'payload' => '{"oldPayload":true}',
            'invalid' => 0,
            'module_types' => null,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $this->connection->insert('rule_condition', [
            'id' => $this->oldConditionId,
            'rule_id' => $this->oldRuleId,
            'parent_id' => null,
            'type' => 'cartLineItemStock',
            'value' => json_encode([
                'operator' => '>=',
                'stock' => 10,
            ], \JSON_THROW_ON_ERROR),
            'position' => 1,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $this->connection->insert('rule', [
            'id' => $this->otherRuleId,
            'name' => 'actual stock rule',
            'priority' => 1,
            'payload' => '{"keep":true}',
            'invalid' => 0,
            'module_types' => null,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $this->connection->insert('rule_condition', [
            'id' => $this->otherConditionId,
            'rule_id' => $this->otherRuleId,
            'parent_id' => null,
            'type' => 'cartLineItemActualStock',
            'value' => json_encode([
                'operator' => '>=',
                'stock' => 5,
            ], \JSON_THROW_ON_ERROR),
            'position' => 1,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);
    }
}
