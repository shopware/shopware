<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_5\Migration1669291632MigrateLineItemsInCartRule;

/**
 * @internal
 */
#[CoversClass(Migration1669291632MigrateLineItemsInCartRule::class)]
class Migration1669291632MigrateLineItemsInCartRuleTest extends TestCase
{
    private Connection $connection;

    private Migration1669291632MigrateLineItemsInCartRule $migration;

    /**
     * @var array<string, mixed>
     */
    private array $testRule;

    /**
     * @var array<string, mixed>
     */
    private array $testCondition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1669291632MigrateLineItemsInCartRule();

        $this->testRule = [
            'id' => Uuid::randomBytes(),
            'name' => 'testMigrateLineItemsInCartRule',
            'priority' => 1,
            'payload' => 'someValue',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
        $this->testCondition = [
            'id' => Uuid::randomBytes(),
            'rule_id' => $this->testRule['id'],
            'type' => 'cartLineItemsInCart',
            'value' => '{"operator":"=","identifiers":["001235290242435795391d026fa03b5b"]}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
    }

    public function testUpdate(): void
    {
        $this->addTestConditions();
        static::assertGreaterThanOrEqual(1, $this->getLineItemsInCartRuleConditions());

        $this->migration->update($this->connection);
        static::assertCount(0, $this->getLineItemsInCartRuleConditions());
        static::assertNull($this->getTestRule()['payload'], 'the migrated rule payload should be empty');

        $this->removeTestConditions();
    }

    /**
     * @return array<string, mixed>[]
     */
    private function getLineItemsInCartRuleConditions(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM rule_condition WHERE type="cartLineItemsInCart"');
    }

    /**
     * @return array<string|int, mixed>
     */
    private function getTestRule(): array
    {
        return (array) $this->connection->fetchAssociative('SELECT * FROM rule WHERE id = :id', [
            'id' => $this->testRule['id'],
        ]);
    }

    private function addTestConditions(): void
    {
        $this->connection->insert('rule', $this->testRule);
        $this->connection->insert('rule_condition', $this->testCondition);
    }

    private function removeTestConditions(): void
    {
        $this->connection->delete('rule_condition', [
            'id' => $this->testCondition['id'],
        ]);
        $this->connection->delete('rule', [
            'id' => $this->testRule['id'],
        ]);
    }
}
