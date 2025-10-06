<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_7\Migration1759759287UpdateLineItemPurchasePriceRuleConditions;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1759759287UpdateLineItemPurchasePriceRuleConditions::class)]
class Migration1759759287UpdateLineItemPurchasePriceRuleConditionsTest extends TestCase
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

        $migration = new Migration1759759287UpdateLineItemPurchasePriceRuleConditions();
        $migration->update($this->connection);
        $migration->update($this->connection);
    }

    public function testShouldUpdateLineItemPurchasePriceRuleValues(): void
    {
        static::assertEqualsCanonicalizing(7, $this->getConditionCount());

        $conditionValues = $this->getConditionValues();
        static::assertCount(7, $conditionValues);

        static::assertArrayHasKey($this->ids->getBytes('rule-condition-1'), $conditionValues);
        static::assertArrayHasKey($this->ids->getBytes('rule-condition-2'), $conditionValues);

        static::assertEqualsCanonicalizing(
            [
                'type' => 'net',
                'amount' => 100,
                'operator' => '=',
            ],
            $conditionValues[$this->ids->getBytes('rule-condition-1')],
        );

        static::assertEqualsCanonicalizing(
            [
                'type' => 'gross',
                'amount' => 101,
            ],
            $conditionValues[$this->ids->getBytes('rule-condition-2')],
        );

        static::assertEqualsCanonicalizing(
            [
                'type' => 'gross',
            ],
            $conditionValues[$this->ids->getBytes('rule-condition-3')],
        );
    }

    public function testShouldNotUpdateConditionsWithOtherTypes(): void
    {
        $conditionValues = $this->getConditionValues();
        static::assertCount(7, $conditionValues);

        static::assertArrayHasKey($this->ids->getBytes('rule-condition-4'), $conditionValues);

        static::assertEqualsCanonicalizing(
            ['isNet' => true],
            $conditionValues[$this->ids->getBytes('rule-condition-4')],
        );
    }

    public function testShouldNotUpdateConditionsWithNoMatchingValue(): void
    {
        $conditionValues = $this->getConditionValues();
        static::assertCount(7, $conditionValues);

        static::assertArrayHasKey($this->ids->getBytes('rule-condition-4'), $conditionValues);
        static::assertArrayHasKey($this->ids->getBytes('rule-condition-5'), $conditionValues);
        static::assertArrayHasKey($this->ids->getBytes('rule-condition-6'), $conditionValues);

        static::assertEqualsCanonicalizing(
            ['isNet' => true],
            $conditionValues[$this->ids->getBytes('rule-condition-4')],
        );

        static::assertEqualsCanonicalizing(
            ['something' => 'product'],
            $conditionValues[$this->ids->getBytes('rule-condition-5')],
        );

        static::assertEqualsCanonicalizing(
            ['isNet' => 1],
            $conditionValues[$this->ids->getBytes('rule-condition-6')],
        );

        static::assertEqualsCanonicalizing(
            [],
            $conditionValues[$this->ids->getBytes('rule-condition-7')],
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
            'type' => 'cartLineItemPurchasePrice',
            'value' => '{"isNet":true, "amount":100, "operator":"="}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // second target
        static::getContainer()->get(Connection::class)->insert('rule_condition', [
            'id' => $this->ids->getBytes('rule-condition-2'),
            'rule_id' => $this->ids->getBytes('rule'),
            'type' => 'cartLineItemPurchasePrice',
            'value' => '{"isNet":false, "amount":101}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // third target
        static::getContainer()->get(Connection::class)->insert('rule_condition', [
            'id' => $this->ids->getBytes('rule-condition-3'),
            'rule_id' => $this->ids->getBytes('rule'),
            'type' => 'cartLineItemPurchasePrice',
            'value' => '{"isNet":false}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // other condition type
        static::getContainer()->get(Connection::class)->insert('rule_condition', [
            'id' => $this->ids->getBytes('rule-condition-4'),
            'rule_id' => $this->ids->getBytes('rule'),
            'type' => 'customerDefaultPaymentMethod',
            'value' => '{"isNet":true}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // no matching condition value
        static::getContainer()->get(Connection::class)->insert('rule_condition', [
            'id' => $this->ids->getBytes('rule-condition-5'),
            'rule_id' => $this->ids->getBytes('rule'),
            'type' => 'cartLineItemPurchasePrice',
            'value' => '{"something":"product"}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // no matching condition value type
        static::getContainer()->get(Connection::class)->insert('rule_condition', [
            'id' => $this->ids->getBytes('rule-condition-6'),
            'rule_id' => $this->ids->getBytes('rule'),
            'type' => 'cartLineItemPurchasePrice',
            'value' => '{"isNet":1}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // empty condition value
        static::getContainer()->get(Connection::class)->insert('rule_condition', [
            'id' => $this->ids->getBytes('rule-condition-7'),
            'rule_id' => $this->ids->getBytes('rule'),
            'type' => 'cartLineItemPurchasePrice',
            'value' => '[]',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
