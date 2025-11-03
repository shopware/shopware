<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_7\Migration1754398573ChangeAllLineItemsRuleValueType;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1754398573ChangeAllLineItemsRuleValueType::class)]
class Migration1754398573ChangeAllLineItemsRuleValueTypeTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->ids = new IdsCollection();

        $this->addTestConditions();

        $migration = new Migration1754398573ChangeAllLineItemsRuleValueType();
        $migration->update($this->connection);
        $migration->update($this->connection);
    }

    public function testShouldUpdateAllLineItemsRuleValue(): void
    {
        static::assertSame(6, $this->getConditionCount());

        $conditionValues = $this->getConditionValues();
        static::assertCount(6, $conditionValues);

        static::assertArrayHasKey($this->ids->getBytes('rule-condition-1'), $conditionValues);
        static::assertArrayHasKey($this->ids->getBytes('rule-condition-2'), $conditionValues);

        static::assertSame(
            ['types' => ['product']],
            $conditionValues[$this->ids->getBytes('rule-condition-1')],
        );

        static::assertSame(
            ['types' => ['something']],
            $conditionValues[$this->ids->getBytes('rule-condition-2')],
        );
    }

    public function testShouldNotUpdateConditionsWithOtherTypes(): void
    {
        $conditionValues = $this->getConditionValues();
        static::assertCount(6, $conditionValues);

        static::assertArrayHasKey($this->ids->getBytes('rule-condition-3'), $conditionValues);

        static::assertSame(
            ['type' => 'product'],
            $conditionValues[$this->ids->getBytes('rule-condition-3')],
        );
    }

    public function testShouldNotUpdateConditionsWithNoMatchingValue(): void
    {
        $conditionValues = $this->getConditionValues();
        static::assertCount(6, $conditionValues);

        static::assertArrayHasKey($this->ids->getBytes('rule-condition-4'), $conditionValues);
        static::assertArrayHasKey($this->ids->getBytes('rule-condition-5'), $conditionValues);
        static::assertArrayHasKey($this->ids->getBytes('rule-condition-6'), $conditionValues);

        static::assertSame(
            ['something' => 'product'],
            $conditionValues[$this->ids->getBytes('rule-condition-4')],
        );

        static::assertSame(
            ['type' => 1],
            $conditionValues[$this->ids->getBytes('rule-condition-5')],
        );

        static::assertSame(
            [],
            $conditionValues[$this->ids->getBytes('rule-condition-6')],
        );
    }

    private function getConditionCount(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `rule_condition` WHERE `rule_id` = :ruleId',
            ['ruleId' => $this->ids->getBytes('rule')]
        );
    }

    /**
     * @return mixed[]
     */
    private function getConditionValues(): array
    {
        return array_map(
            fn (string $json) => json_decode($json, true),
            static::getContainer()->get(Connection::class)->fetchAllKeyValue(
                'SELECT `id`, `value` FROM `rule_condition` WHERE `rule_id` = :ruleId',
                ['ruleId' => $this->ids->getBytes('rule')]
            )
        );
    }

    private function addTestConditions(): void
    {
        static::getContainer()->get(Connection::class)->insert('rule', [
            'id' => $this->ids->getBytes('rule'),
            'name' => 'some rule',
            'priority' => 1,
            'payload' => 'some value',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // first target
        static::getContainer()->get(Connection::class)->insert('rule_condition', [
            'id' => $this->ids->getBytes('rule-condition-1'),
            'rule_id' => $this->ids->getBytes('rule'),
            'type' => 'allLineItemsContainer',
            'value' => '{"type":"product"}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // second target
        static::getContainer()->get(Connection::class)->insert('rule_condition', [
            'id' => $this->ids->getBytes('rule-condition-2'),
            'rule_id' => $this->ids->getBytes('rule'),
            'type' => 'allLineItemsContainer',
            'value' => '{"type":"something"}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // other condition type
        static::getContainer()->get(Connection::class)->insert('rule_condition', [
            'id' => $this->ids->getBytes('rule-condition-3'),
            'rule_id' => $this->ids->getBytes('rule'),
            'type' => 'customerDefaultPaymentMethod',
            'value' => '{"type":"product"}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // no matching condition value
        static::getContainer()->get(Connection::class)->insert('rule_condition', [
            'id' => $this->ids->getBytes('rule-condition-4'),
            'rule_id' => $this->ids->getBytes('rule'),
            'type' => 'allLineItemsContainer',
            'value' => '{"something":"product"}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // no matching condition value type
        static::getContainer()->get(Connection::class)->insert('rule_condition', [
            'id' => $this->ids->getBytes('rule-condition-5'),
            'rule_id' => $this->ids->getBytes('rule'),
            'type' => 'allLineItemsContainer',
            'value' => '{"type":1}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // empty condition value
        static::getContainer()->get(Connection::class)->insert('rule_condition', [
            'id' => $this->ids->getBytes('rule-condition-6'),
            'rule_id' => $this->ids->getBytes('rule'),
            'type' => 'allLineItemsContainer',
            'value' => '[]',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
