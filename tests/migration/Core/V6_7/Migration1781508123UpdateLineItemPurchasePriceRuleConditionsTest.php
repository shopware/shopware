<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_7\Migration1781508123UpdateLineItemPurchasePriceRuleConditions;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1781508123UpdateLineItemPurchasePriceRuleConditions::class)]
class Migration1781508123UpdateLineItemPurchasePriceRuleConditionsTest extends TestCase
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

        $migration = new Migration1781508123UpdateLineItemPurchasePriceRuleConditions();
        $migration->update($this->connection);
        $migration->update($this->connection);
    }

    public function testShouldUpdateLineItemPurchasePriceRuleValues(): void
    {
        $conditionValues = $this->getConditionValues();
        static::assertCount(7, $conditionValues);

        static::assertEquals(
            [
                'type' => 'net',
                'amount' => 100,
                'operator' => '=',
            ],
            $conditionValues[$this->ids->getBytes('rule-condition-1')],
        );

        static::assertEquals(
            [
                'type' => 'gross',
                'amount' => 101,
            ],
            $conditionValues[$this->ids->getBytes('rule-condition-2')],
        );

        static::assertEquals(
            [
                'type' => 'gross',
            ],
            $conditionValues[$this->ids->getBytes('rule-condition-3')],
        );
    }

    public function testShouldNotUpdateConditionsWithOtherTypes(): void
    {
        $conditionValues = $this->getConditionValues();

        static::assertEquals(
            ['isNet' => true],
            $conditionValues[$this->ids->getBytes('rule-condition-4')],
        );
    }

    public function testShouldNotUpdateConditionsWithNoMatchingValue(): void
    {
        $conditionValues = $this->getConditionValues();

        static::assertEquals(
            ['something' => 'product'],
            $conditionValues[$this->ids->getBytes('rule-condition-5')],
        );

        static::assertEquals(
            ['isNet' => 1],
            $conditionValues[$this->ids->getBytes('rule-condition-6')],
        );

        static::assertEquals(
            [],
            $conditionValues[$this->ids->getBytes('rule-condition-7')],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getConditionValues(): array
    {
        return array_map(
            fn (string $json) => json_decode($json, true, 512, \JSON_THROW_ON_ERROR),
            $this->connection->fetchAllKeyValue(
                'SELECT `id`, `value` FROM `rule_condition` WHERE `rule_id` = :ruleId',
                ['ruleId' => $this->ids->getBytes('rule')]
            )
        );
    }

    private function addTestConditions(): void
    {
        $this->connection->insert('rule', [
            'id' => $this->ids->getBytes('rule'),
            'name' => 'some rule',
            'priority' => 1,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $conditions = [
            'rule-condition-1' => ['cartLineItemPurchasePrice', '{"isNet":true, "amount":100, "operator":"="}'],
            'rule-condition-2' => ['cartLineItemPurchasePrice', '{"isNet":false, "amount":101}'],
            'rule-condition-3' => ['cartLineItemPurchasePrice', '{"isNet":false}'],
            'rule-condition-4' => ['customerDefaultPaymentMethod', '{"isNet":true}'],
            'rule-condition-5' => ['cartLineItemPurchasePrice', '{"something":"product"}'],
            'rule-condition-6' => ['cartLineItemPurchasePrice', '{"isNet":1}'],
            'rule-condition-7' => ['cartLineItemPurchasePrice', '[]'],
        ];

        foreach ($conditions as $key => [$type, $value]) {
            $this->connection->insert('rule_condition', [
                'id' => $this->ids->getBytes($key),
                'rule_id' => $this->ids->getBytes('rule'),
                'type' => $type,
                'value' => $value,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }
    }
}
