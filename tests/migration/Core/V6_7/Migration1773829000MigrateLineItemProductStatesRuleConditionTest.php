<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1773829000MigrateLineItemProductStatesRuleCondition;

/**
 * @internal
 */
#[CoversClass(Migration1773829000MigrateLineItemProductStatesRuleCondition::class)]
class Migration1773829000MigrateLineItemProductStatesRuleConditionTest extends TestCase
{
    private Connection $connection;

    private string $ruleId;

    private string $digitalConditionId;

    private string $legacyConditionId;

    private string $noProductStateConditionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();

        $this->ruleId = Uuid::randomBytes();
        $this->digitalConditionId = Uuid::randomBytes();
        $this->legacyConditionId = Uuid::randomBytes();
        $this->noProductStateConditionId = Uuid::randomBytes();
    }

    protected function tearDown(): void
    {
        $this->connection->delete('rule_condition', ['id' => $this->digitalConditionId]);
        $this->connection->delete('rule_condition', ['id' => $this->legacyConditionId]);
        $this->connection->delete('rule_condition', ['id' => $this->noProductStateConditionId]);
        $this->connection->delete('rule', ['id' => $this->ruleId]);

        parent::tearDown();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1773829000MigrateLineItemProductStatesRuleCondition();

        static::assertSame(1773829000, $migration->getCreationTimestamp());
    }

    public function testUpdateIsNoOp(): void
    {
        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('rule', [
            'id' => $this->ruleId,
            'name' => 'legacy product states rule',
            'priority' => 1,
            'payload' => null,
            'invalid' => 0,
            'module_types' => null,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $this->connection->insert('rule_condition', [
            'id' => $this->digitalConditionId,
            'rule_id' => $this->ruleId,
            'parent_id' => null,
            'type' => 'cartLineItemProductStates',
            'value' => json_encode(['operator' => '=', 'productState' => 'is-download'], \JSON_THROW_ON_ERROR),
            'position' => 1,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $migration = new Migration1773829000MigrateLineItemProductStatesRuleCondition();
        $migration->update($this->connection);

        // update() must not convert conditions, conversion was moved to updateDestructive()
        static::assertSame(
            'cartLineItemProductStates',
            $this->connection->fetchOne(
                'SELECT `type` FROM `rule_condition` WHERE `id` = :id',
                ['id' => $this->digitalConditionId]
            )
        );
    }

    public function testMigration(): void
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

        $this->connection->insert('rule_condition', [
            'id' => $this->digitalConditionId,
            'rule_id' => $this->ruleId,
            'parent_id' => null,
            'type' => 'cartLineItemProductStates',
            'value' => json_encode([
                'operator' => '=',
                'productState' => 'is-download',
            ], \JSON_THROW_ON_ERROR),
            'position' => 1,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $this->connection->insert('rule_condition', [
            'id' => $this->legacyConditionId,
            'rule_id' => $this->ruleId,
            'parent_id' => null,
            'type' => 'cartLineItemProductStates',
            'value' => json_encode([
                'operator' => '=',
                'productState' => 'is-some-legacy-state',
            ], \JSON_THROW_ON_ERROR),
            'position' => 2,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $this->connection->insert('rule_condition', [
            'id' => $this->noProductStateConditionId,
            'rule_id' => $this->ruleId,
            'parent_id' => null,
            'type' => 'cartLineItemProductStates',
            'value' => json_encode([
                'operator' => '=',
                'foo' => 'bar',
            ], \JSON_THROW_ON_ERROR),
            'position' => 3,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $migration = new Migration1773829000MigrateLineItemProductStatesRuleCondition();

        // make sure the migration is idempotent
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        $digitalCondition = $this->connection->fetchAssociative(
            'SELECT `type`, `value` FROM `rule_condition` WHERE `id` = :id',
            ['id' => $this->digitalConditionId]
        );

        static::assertIsArray($digitalCondition);
        static::assertSame('cartLineItemProductType', $digitalCondition['type']);
        static::assertIsString($digitalCondition['value']);
        static::assertSame(
            [
                'operator' => '=',
                'productType' => 'digital',
            ],
            json_decode($digitalCondition['value'], true, 512, \JSON_THROW_ON_ERROR)
        );

        $legacyCondition = $this->connection->fetchAssociative(
            'SELECT `type`, `value` FROM `rule_condition` WHERE `id` = :id',
            ['id' => $this->legacyConditionId]
        );

        static::assertIsArray($legacyCondition);
        static::assertSame('cartLineItemProductStates', $legacyCondition['type']);

        $noProductStateCondition = $this->connection->fetchAssociative(
            'SELECT `type`, `value` FROM `rule_condition` WHERE `id` = :id',
            ['id' => $this->noProductStateConditionId]
        );

        static::assertIsArray($noProductStateCondition);
        static::assertSame('cartLineItemProductStates', $noProductStateCondition['type']);

        static::assertSame(
            '{"legacy":true}',
            $this->connection->fetchOne(
                'SELECT `payload` FROM `rule` WHERE `id` = :id',
                ['id' => $this->ruleId]
            )
        );

        static::assertSame(
            '2',
            (string) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM `rule_condition` WHERE `rule_id` = :ruleId AND `type` = :type',
                ['ruleId' => $this->ruleId, 'type' => 'cartLineItemProductStates']
            )
        );

        $indexers = (new IndexerQueuer($this->connection))->getIndexers();
        static::assertArrayHasKey('rule.indexer', $indexers);
    }
}
