<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_8\Migration1773829003RemoveLegacyLineItemProductStatesRuleCondition;

/**
 * @internal
 */
#[CoversClass(Migration1773829003RemoveLegacyLineItemProductStatesRuleCondition::class)]
class Migration1773829003RemoveLegacyLineItemProductStatesRuleConditionTest extends TestCase
{
    private Connection $connection;
    private string $ruleId;
    private string $legacyConditionId;
    private string $newConditionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->ruleId = Uuid::randomBytes();
        $this->legacyConditionId = Uuid::randomBytes();
        $this->newConditionId = Uuid::randomBytes();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1773829003RemoveLegacyLineItemProductStatesRuleCondition();

        static::assertSame(1773829003, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('rule', [
            'id' => $this->ruleId,
            'name' => 'rule for cleanup migration',
            'priority' => 1,
            'payload' => '{"legacy":true}',
            'invalid' => 0,
            'module_types' => null,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $this->connection->insert('rule_condition', [
            'id' => $this->legacyConditionId,
            'rule_id' => $this->ruleId,
            'parent_id' => null,
            'type' => 'cartLineItemProductStates',
            'value' => '{"operator":"=","productState":"is-legacy"}',
            'position' => 1,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $this->connection->insert('rule_condition', [
            'id' => $this->newConditionId,
            'rule_id' => $this->ruleId,
            'parent_id' => null,
            'type' => 'cartLineItemProductType',
            'value' => '{"operator":"=","productType":"digital"}',
            'position' => 2,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $migration = new Migration1773829003RemoveLegacyLineItemProductStatesRuleCondition();

        // make sure the migration is idempotent
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(
            '0',
            (string) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM `rule_condition` WHERE `type` = :type',
                ['type' => 'cartLineItemProductStates']
            )
        );

        static::assertSame(
            '1',
            (string) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM `rule_condition` WHERE `id` = :id AND `type` = :type',
                ['id' => $this->newConditionId, 'type' => 'cartLineItemProductType']
            )
        );

        $indexers = (new IndexerQueuer($this->connection))->getIndexers();
        static::assertArrayHasKey('rule.indexer', $indexers);
    }

    protected function tearDown(): void
    {
        $this->connection->delete('rule_condition', ['id' => $this->legacyConditionId]);
        $this->connection->delete('rule_condition', ['id' => $this->newConditionId]);
        $this->connection->delete('rule', ['id' => $this->ruleId]);

        parent::tearDown();
    }
}
