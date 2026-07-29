<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_7\Migration1777030522FixContainerStructureOfDefaultRuleConditions;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1777030522FixContainerStructureOfDefaultRuleConditions::class)]
class Migration1777030522FixContainerStructureOfDefaultRuleConditionsTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = static::getContainer()->get(Connection::class);
        $this->ids = new IdsCollection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1777030522, (new Migration1777030522FixContainerStructureOfDefaultRuleConditions())->getCreationTimestamp());
    }

    public function testUpdateWrapsLegacyDefaultRuleConditions(): void
    {
        $this->addLegacyRule('cart', 'Cart >= 0', 100, 'cartCartAmount', ['operator' => '>=', 'amount' => 0]);
        $this->addLegacyRule('alwaysValid', 'Always valid (Default)', 100, 'alwaysValid', null);
        $this->addLegacyRule('alwaysValidWithValue', 'Always valid (Default)', 100, 'alwaysValid', ['isAlwaysValid' => true]);

        $this->addDigitalProductsRule();

        $this->addLegacyRule('customCart', 'Cart >= 0', 100, 'cartCartAmount', ['operator' => '>', 'amount' => 0]);
        $this->addLegacyRule('customAlwaysValid', 'Always valid (Default)', 100, 'alwaysValid', ['isAlwaysValid' => false]);

        $this->addStructuredRule();

        $migration = new Migration1777030522FixContainerStructureOfDefaultRuleConditions();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $this->assertWrappedRule('cart', 'cartCartAmount', ['operator' => '>=', 'amount' => 0]);
        $this->assertWrappedRule('alwaysValid', 'alwaysValid', null);
        $this->assertWrappedRule('alwaysValidWithValue', 'alwaysValid', ['isAlwaysValid' => true]);

        $this->assertDigitalProductsRuleIsUntouched();

        $this->assertLegacyRootRule('customCart', 'cartCartAmount', ['operator' => '>', 'amount' => 0]);
        $this->assertLegacyRootRule('customAlwaysValid', 'alwaysValid', ['isAlwaysValid' => false]);

        static::assertSame('structured-payload', $this->getRulePayload('structured'));
        static::assertSame('digitalProducts-payload', $this->getRulePayload('digitalProducts'));

        static::assertNull($this->getRulePayload('cart'));
        static::assertNull($this->getRulePayload('alwaysValid'));
        static::assertNull($this->getRulePayload('alwaysValidWithValue'));
    }

    /**
     * @param array<string, mixed>|null $value
     */
    private function addLegacyRule(
        string $key,
        string $name,
        int $priority,
        string $conditionType,
        ?array $value
    ): void {
        $this->connection->insert('rule', [
            'id' => $this->ids->getBytes($key . 'Rule'),
            'name' => $name,
            'priority' => $priority,
            'invalid' => 0,
            'payload' => $key . '-payload',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('rule_condition', [
            'id' => $this->ids->getBytes($key . 'Condition'),
            'rule_id' => $this->ids->getBytes($key . 'Rule'),
            'type' => $conditionType,
            'value' => $value === null ? null : json_encode($value, \JSON_THROW_ON_ERROR),
            'position' => 12,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function addDigitalProductsRule(): void
    {
        $this->connection->insert('rule', [
            'id' => $this->ids->getBytes('digitalProductsRule'),
            'name' => 'Shopping cart / Order with digital products',
            'priority' => 1,
            'invalid' => 0,
            'payload' => 'digitalProducts-payload',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('rule_condition', [
            'id' => $this->ids->getBytes('digitalProductsAndCondition'),
            'rule_id' => $this->ids->getBytes('digitalProductsRule'),
            'type' => 'andContainer',
            'value' => '[]',
            'position' => 12,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('rule_condition', [
            'id' => $this->ids->getBytes('digitalProductsCondition'),
            'rule_id' => $this->ids->getBytes('digitalProductsRule'),
            'parent_id' => $this->ids->getBytes('digitalProductsAndCondition'),
            'type' => 'cartLineItemProductStates',
            'value' => json_encode(
                ['operator' => '=', 'productState' => 'is-download'],
                \JSON_THROW_ON_ERROR
            ),
            'position' => 0,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function addStructuredRule(): void
    {
        $this->connection->insert('rule', [
            'id' => $this->ids->getBytes('structuredRule'),
            'name' => 'All customers',
            'priority' => 100,
            'invalid' => 0,
            'payload' => 'structured-payload',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('rule_condition', [
            'id' => $this->ids->getBytes('structuredOrCondition'),
            'rule_id' => $this->ids->getBytes('structuredRule'),
            'type' => 'orContainer',
            'position' => 0,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('rule_condition', [
            'id' => $this->ids->getBytes('structuredAndCondition'),
            'rule_id' => $this->ids->getBytes('structuredRule'),
            'parent_id' => $this->ids->getBytes('structuredOrCondition'),
            'type' => 'andContainer',
            'position' => 0,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('rule_condition', [
            'id' => $this->ids->getBytes('structuredCondition'),
            'rule_id' => $this->ids->getBytes('structuredRule'),
            'parent_id' => $this->ids->getBytes('structuredAndCondition'),
            'type' => 'customerCustomerGroup',
            'value' => json_encode([
                'operator' => '=',
                'customerGroupIds' => ['cfbd5018d38d41d8adca10d94fc8bdd6'],
            ], \JSON_THROW_ON_ERROR),
            'position' => 0,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * @param array<string, mixed>|null $value
     */
    private function assertWrappedRule(string $key, string $conditionType, ?array $value): void
    {
        $conditions = $this->getConditions($key);

        static::assertCount(3, $conditions);

        $orContainer = $this->conditionByType($conditions, 'orContainer');
        $andContainer = $this->conditionByType($conditions, 'andContainer');
        $condition = $this->conditionByType($conditions, $conditionType);

        static::assertNull($orContainer['parentId']);
        static::assertSame(0, (int) $orContainer['position']);
        static::assertSame($orContainer['id'], $andContainer['parentId']);
        static::assertSame(0, (int) $andContainer['position']);
        static::assertSame($andContainer['id'], $condition['parentId']);
        static::assertSame(0, (int) $condition['position']);

        if ($value === null) {
            static::assertNull($condition['value']);

            return;
        }

        static::assertEquals(
            $value,
            json_decode((string) $condition['value'], true, flags: \JSON_THROW_ON_ERROR)
        );
    }

    private function assertDigitalProductsRuleIsUntouched(): void
    {
        $conditions = $this->getConditions('digitalProducts');

        static::assertCount(2, $conditions);

        $andContainer = $this->conditionByType($conditions, 'andContainer');
        $condition = $this->conditionByType($conditions, 'cartLineItemProductStates');

        static::assertNull($andContainer['parentId']);
        static::assertSame(12, (int) $andContainer['position']);
        static::assertSame($andContainer['id'], $condition['parentId']);

        static::assertSame(
            ['operator' => '=', 'productState' => 'is-download'],
            json_decode((string) $condition['value'], true, flags: \JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @param array<string, mixed> $value
     */
    private function assertLegacyRootRule(string $key, string $conditionType, array $value): void
    {
        $conditions = $this->getConditions($key);

        static::assertCount(1, $conditions);
        static::assertSame($conditionType, $conditions[0]['type']);
        static::assertNull($conditions[0]['parentId']);
        static::assertSame(12, (int) $conditions[0]['position']);
        static::assertEquals(
            $value,
            json_decode((string) $conditions[0]['value'], true, flags: \JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @return list<array{id: string, parentId: string|null, type: string, value: string|null, position: int|string}>
     */
    private function getConditions(string $key): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`id`)) AS `id`, LOWER(HEX(`parent_id`)) AS `parentId`, `type`, `value`, `position`
             FROM `rule_condition`
             WHERE `rule_id` = :ruleId',
            ['ruleId' => $this->ids->getBytes($key . 'Rule')]
        );

        return array_map(static fn (array $row): array => [
            'id' => (string) $row['id'],
            'parentId' => \is_string($row['parentId']) ? $row['parentId'] : null,
            'type' => (string) $row['type'],
            'value' => \is_string($row['value']) ? $row['value'] : null,
            'position' => \is_int($row['position']) || \is_string($row['position']) ? $row['position'] : 0,
        ], $rows);
    }

    /**
     * @param list<array{id: string, parentId: string|null, type: string, value: string|null, position: int|string}> $conditions
     *
     * @return array{id: string, parentId: string|null, type: string, value: string|null, position: int|string}
     */
    private function conditionByType(array $conditions, string $type): array
    {
        foreach ($conditions as $condition) {
            if ($condition['type'] === $type) {
                return $condition;
            }
        }

        static::fail();
    }

    private function getRulePayload(string $key): ?string
    {
        $payload = $this->connection->fetchOne(
            'SELECT `payload` FROM `rule` WHERE `id` = :id',
            ['id' => $this->ids->getBytes($key . 'Rule')]
        );

        return \is_string($payload) ? $payload : null;
    }
}
