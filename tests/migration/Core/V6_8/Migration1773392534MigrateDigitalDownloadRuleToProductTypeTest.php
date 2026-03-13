<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_8\Migration1773392534MigrateDigitalDownloadRuleToProductType;

/**
 * @internal
 */
#[CoversClass(Migration1773392534MigrateDigitalDownloadRuleToProductType::class)]
class Migration1773392534MigrateDigitalDownloadRuleToProductTypeTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(
            1773392534,
            (new Migration1773392534MigrateDigitalDownloadRuleToProductType())->getCreationTimestamp()
        );
    }

    public function testMigrationUpdatesOnlyDownloadStateRuleCondition(): void
    {
        $ruleId = Uuid::randomBytes();
        $matchingConditionId = Uuid::randomBytes();
        $nonMatchingConditionId = Uuid::randomBytes();

        $this->connection->insert('rule', [
            'id' => $ruleId,
            'name' => 'Migration1773392534 test rule',
            'priority' => 1,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);

        $this->connection->insert('rule_condition', [
            'id' => $matchingConditionId,
            'type' => 'cartLineItemProductStates',
            'rule_id' => $ruleId,
            'value' => json_encode(['operator' => '=', 'productState' => 'is-download'], \JSON_THROW_ON_ERROR),
            'position' => 1,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);

        $this->connection->insert('rule_condition', [
            'id' => $nonMatchingConditionId,
            'type' => 'cartLineItemProductStates',
            'rule_id' => $ruleId,
            'value' => json_encode(['operator' => '=', 'productState' => 'is-physical'], \JSON_THROW_ON_ERROR),
            'position' => 2,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);

        $migration = new Migration1773392534MigrateDigitalDownloadRuleToProductType();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $matchingCondition = $this->connection->fetchAssociative(
            'SELECT type, value FROM rule_condition WHERE id = :id',
            ['id' => $matchingConditionId],
            ['id' => Types::BINARY]
        );

        static::assertIsArray($matchingCondition);
        static::assertSame('cartLineItemProductType', $matchingCondition['type']);
        static::assertSame(
            '{"operator":"=","productType":"digital"}',
            json_encode(json_decode((string) $matchingCondition['value'], true, 512, \JSON_THROW_ON_ERROR), \JSON_THROW_ON_ERROR)
        );

        $nonMatchingCondition = $this->connection->fetchAssociative(
            'SELECT type, value FROM rule_condition WHERE id = :id',
            ['id' => $nonMatchingConditionId],
            ['id' => Types::BINARY]
        );

        static::assertIsArray($nonMatchingCondition);
        static::assertSame('cartLineItemProductStates', $nonMatchingCondition['type']);
        static::assertSame(
            '{"operator":"=","productState":"is-physical"}',
            json_encode(json_decode((string) $nonMatchingCondition['value'], true, 512, \JSON_THROW_ON_ERROR), \JSON_THROW_ON_ERROR)
        );

        $this->connection->delete('rule_condition', ['id' => $matchingConditionId], ['id' => Types::BINARY]);
        $this->connection->delete('rule_condition', ['id' => $nonMatchingConditionId], ['id' => Types::BINARY]);
        $this->connection->delete('rule', ['id' => $ruleId], ['id' => Types::BINARY]);
    }
}
