<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_6\Migration1673249981MigrateIsNewCustomerRule;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1673249981MigrateIsNewCustomerRule::class)]
class Migration1673249981MigrateIsNewCustomerRuleTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1673249981MigrateIsNewCustomerRule $migration;

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
        $this->migration = new Migration1673249981MigrateIsNewCustomerRule();

        $this->testRule = [
            'id' => Uuid::randomBytes(),
            'name' => 'testMigrateIsNewCustomerRule',
            'priority' => 1,
            'payload' => 'someValue',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
        $this->testCondition = [
            'id' => Uuid::randomBytes(),
            'rule_id' => $this->testRule['id'],
            'type' => 'customerIsNewCustomer',
            'value' => '{"isNew":true}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
    }

    public function testUpdate(): void
    {
        $this->addTestConditions();
        static::assertGreaterThanOrEqual(1, $this->getIsNewCustomerConditions());

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);
        static::assertCount(0, $this->getIsNewCustomerConditions());
        static::assertNull($this->getTestRule()['payload'], 'the migrated rule payload should be empty');
        $value = json_decode((string) $this->getDaysSinceFirstLoginConditions()['value'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertEquals('=', $value['operator']);
        static::assertEquals(0, $value['daysPassed']);

        $this->removeTestConditions();
    }

    /**
     * @return array<string, mixed>[]
     */
    private function getIsNewCustomerConditions(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM rule_condition WHERE type = "customerIsNewCustomer"');
    }

    /**
     * @return array<string|int, mixed>
     */
    private function getDaysSinceFirstLoginConditions(): array
    {
        return (array) $this->connection->fetchAssociative('SELECT * FROM rule_condition WHERE type = "customerDaysSinceFirstLogin"');
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
