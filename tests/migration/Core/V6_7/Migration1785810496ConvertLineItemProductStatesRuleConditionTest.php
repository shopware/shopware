<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1785810496ConvertLineItemProductStatesRuleCondition;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1785810496ConvertLineItemProductStatesRuleCondition::class)]
class Migration1785810496ConvertLineItemProductStatesRuleConditionTest extends TestCase
{
    private Connection $connection;

    private string $ruleId;

    private string $digitalConditionId;

    private string $physicalConditionId;

    private string $unknownStateConditionId;

    private string $noProductStateConditionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();

        $this->ruleId = Uuid::randomBytes();
        $this->digitalConditionId = Uuid::randomBytes();
        $this->physicalConditionId = Uuid::randomBytes();
        $this->unknownStateConditionId = Uuid::randomBytes();
        $this->noProductStateConditionId = Uuid::randomBytes();
    }

    protected function tearDown(): void
    {
        $this->connection->delete('rule_condition', ['id' => $this->digitalConditionId]);
        $this->connection->delete('rule_condition', ['id' => $this->physicalConditionId]);
        $this->connection->delete('rule_condition', ['id' => $this->unknownStateConditionId]);
        $this->connection->delete('rule_condition', ['id' => $this->noProductStateConditionId]);
        $this->connection->delete('rule', ['id' => $this->ruleId]);

        parent::tearDown();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1785810496ConvertLineItemProductStatesRuleCondition();

        static::assertSame(1785810496, $migration->getCreationTimestamp());
    }

    public function testUpdateConvertsLegacyConditions(): void
    {
        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('rule', [
            'id' => $this->ruleId,
            'name' => 'legacy product states rule',
            'priority' => 1,
            'payload' => '{"legacy":true}',
            'invalid' => 0,
            'module_types' => null,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $this->insertCondition($this->digitalConditionId, 1, ['operator' => '=', 'productState' => 'is-download'], $createdAt);
        $this->insertCondition($this->physicalConditionId, 2, ['operator' => '!=', 'productState' => 'is-physical'], $createdAt);
        $this->insertCondition($this->unknownStateConditionId, 3, ['operator' => '=', 'productState' => 'is-some-legacy-state'], $createdAt);
        $this->insertCondition($this->noProductStateConditionId, 4, ['operator' => '=', 'foo' => 'bar'], $createdAt);

        $migration = new Migration1785810496ConvertLineItemProductStatesRuleCondition();

        // make sure the migration is idempotent
        $migration->update($this->connection);
        $migration->update($this->connection);

        $this->assertCondition(
            $this->digitalConditionId,
            'cartLineItemProductType',
            ['operator' => '=', 'productType' => 'digital']
        );

        $this->assertCondition(
            $this->physicalConditionId,
            'cartLineItemProductType',
            ['operator' => '!=', 'productType' => 'physical']
        );

        // states without a product type counterpart are left untouched for manual review
        $this->assertCondition(
            $this->unknownStateConditionId,
            'cartLineItemProductStates',
            ['operator' => '=', 'productState' => 'is-some-legacy-state']
        );

        $this->assertCondition(
            $this->noProductStateConditionId,
            'cartLineItemProductStates',
            ['operator' => '=', 'foo' => 'bar']
        );

        static::assertSame(
            '{"legacy":true}',
            $this->connection->fetchOne(
                'SELECT `payload` FROM `rule` WHERE `id` = :id',
                ['id' => $this->ruleId]
            )
        );

        $indexers = (new IndexerQueuer($this->connection))->getIndexers();
        static::assertArrayHasKey('rule.indexer', $indexers);
    }

    /**
     * @param array<string, string> $value
     */
    private function insertCondition(string $id, int $position, array $value, string $createdAt): void
    {
        $this->connection->insert('rule_condition', [
            'id' => $id,
            'rule_id' => $this->ruleId,
            'parent_id' => null,
            'type' => 'cartLineItemProductStates',
            'value' => json_encode($value, \JSON_THROW_ON_ERROR),
            'position' => $position,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);
    }

    /**
     * @param array<string, string> $expectedValue
     */
    private function assertCondition(string $id, string $expectedType, array $expectedValue): void
    {
        $condition = $this->connection->fetchAssociative(
            'SELECT `type`, `value` FROM `rule_condition` WHERE `id` = :id',
            ['id' => $id]
        );

        static::assertIsArray($condition);
        static::assertSame($expectedType, $condition['type']);
        static::assertIsString($condition['value']);

        $value = json_decode($condition['value'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($value);

        // MySQL normalises the key order of JSON columns, MariaDB keeps the written order,
        // so compare the pairs instead of the array order
        ksort($expectedValue);
        ksort($value);

        static::assertSame($expectedValue, $value);
    }
}
