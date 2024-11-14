<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_7\Migration1720603803RemoveDefaultPaymentMethodRule;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[CoversClass(Migration1720603803RemoveDefaultPaymentMethodRule::class)]
class Migration1720603803RemoveDefaultPaymentMethodRuleTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new IdsCollection();
    }

    public function testUpdate(): void
    {
        $this->addTestConditions();
        static::assertCount(2, $this->getConditionValues('customerDefaultPaymentMethod'));

        $migration = new Migration1720603803RemoveDefaultPaymentMethodRule();
        $migration->update($this->getContainer()->get(Connection::class));
        $migration->update($this->getContainer()->get(Connection::class));

        static::assertCount(0, $this->getConditionValues('customerDefaultPaymentMethod'));
        static::assertSame([
            $this->ids->getBytes('ruleCondition1') => ['operator' => '=', 'paymentMethodIds' => ['001235290242435795391d026fa03b5b']],
            $this->ids->getBytes('ruleCondition2') => ['operator' => '=', 'paymentMethodIds' => []],
        ], $this->getConditionValues('paymentMethod'));
        static::assertNull($this->getTestRulePayload(), 'the migrated rule payload should be empty');
    }

    /**
     * @return mixed[]
     */
    private function getConditionValues(string $type): array
    {
        return array_map(
            function (string $json) { return json_decode($json, true); },
            $this->getContainer()->get(Connection::class)->fetchAllKeyValue(
                'SELECT `id`, `value` FROM `rule_condition` WHERE `type`= :type',
                ['type' => $type],
            )
        );
    }

    private function getTestRulePayload(): mixed
    {
        return $this->getContainer()->get(Connection::class)->fetchOne(
            'SELECT `payload` FROM `rule` WHERE `id` = :id',
            [
                'id' => $this->ids->getBytes('rule'),
            ],
        );
    }

    private function addTestConditions(): void
    {
        $this->getContainer()->get(Connection::class)->insert('rule', [
            'id' => $this->ids->getBytes('rule'),
            'name' => 'testRemoveDefaultPaymentMethodRule',
            'priority' => 1,
            'payload' => 'someValue',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
        $this->getContainer()->get(Connection::class)->insert('rule_condition', [
            'id' => $this->ids->getBytes('ruleCondition1'),
            'rule_id' => $this->ids->getBytes('rule'),
            'type' => 'customerDefaultPaymentMethod',
            'value' => '{"operator":"=","methodIds":["001235290242435795391d026fa03b5b"]}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
        $this->getContainer()->get(Connection::class)->insert('rule_condition', [
            'id' => $this->ids->getBytes('ruleCondition2'),
            'rule_id' => $this->ids->getBytes('rule'),
            'type' => 'customerDefaultPaymentMethod',
            'value' => '{"operator":"="}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
