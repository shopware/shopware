<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_6\Migration1728302885MultipleValuesForAffilateAndCampaignCodes;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1728302885MultipleValuesForAffilateAndCampaignCodes::class)]
class Migration1728302885MultipleValuesForAffilateAndCampaignCodesTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1728302885MultipleValuesForAffilateAndCampaignCodes $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1728302885MultipleValuesForAffilateAndCampaignCodes();
    }

    public function testUpdate(): void
    {
        $ids = new IdsCollection();

        $this->connection->insert('rule', [
            'id' => Uuid::fromHexToBytes($ids->create('rule.1')),
            'name' => 'testMigrateCampaignAndAffiliateCodes1',
            'priority' => 1,
            'payload' => 'someValue',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $this->connection->insert('rule', [
            'id' => Uuid::fromHexToBytes($ids->create('rule.2')),
            'name' => 'testMigrateCampaignAndAffiliateCodes2',
            'priority' => 1,
            'payload' => 'someOtherValue',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // Affiliate codes
        $this->connection->insert('rule_condition', [
            'id' => Uuid::fromHexToBytes($ids->create('condition.orderAffiliateCode')),
            'rule_id' => $ids->getBytes('rule.1'),
            'type' => 'orderAffiliateCode',
            'value' => '{"operator":"!=","affiliateCode":"foo"}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $this->connection->insert('rule_condition', [
            'id' => Uuid::fromHexToBytes($ids->create('condition.customerAffiliateCode')),
            'rule_id' => $ids->getBytes('rule.2'),
            'type' => 'customerAffiliateCode',
            'value' => '{"operator":"=","affiliateCode":"bar"}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $this->connection->insert('rule_condition', [
            'id' => Uuid::fromHexToBytes($ids->create('condition.orderAffiliateCode.empty')),
            'rule_id' => $ids->getBytes('rule.1'),
            'type' => 'orderAffiliateCode',
            'value' => '{"operator":"empty"}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $this->connection->insert('rule_condition', [
            'id' => Uuid::fromHexToBytes($ids->create('condition.customerAffiliateCode.empty')),
            'rule_id' => $ids->getBytes('rule.1'),
            'type' => 'customerAffiliateCode',
            'value' => '{"operator":"empty"}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // Campaign codes codes
        $this->connection->insert('rule_condition', [
            'id' => Uuid::fromHexToBytes($ids->create('condition.orderCampaignCode')),
            'rule_id' => $ids->getBytes('rule.2'),
            'type' => 'orderCampaignCode',
            'value' => '{"operator":"!=","campaignCode":"baz"}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $this->connection->insert('rule_condition', [
            'id' => Uuid::fromHexToBytes($ids->create('condition.customerCampaignCode')),
            'rule_id' => $ids->getBytes('rule.1'),
            'type' => 'customerCampaignCode',
            'value' => '{"operator":"=","campaignCode":"asd"}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $this->connection->insert('rule_condition', [
            'id' => Uuid::fromHexToBytes($ids->create('condition.orderCampaignCode.empty')),
            'rule_id' => $ids->getBytes('rule.1'),
            'type' => 'orderCampaignCode',
            'value' => '{"operator":"empty"}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $this->connection->insert('rule_condition', [
            'id' => Uuid::fromHexToBytes($ids->create('condition.customerCampaignCode.empty')),
            'rule_id' => $ids->getBytes('rule.2'),
            'type' => 'customerCampaignCode',
            'value' => '{"operator":"empty"}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // Run the migration, twice
        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        foreach ($ids->getByteList(['rule.1', 'rule.2']) as $ruleId) {
            $rule = $this->getRule($ruleId);
            static::assertNotFalse($rule);
            static::assertNull($rule['payload'], 'the migrated rule payload should be empty');
        }

        $ruleCondition = $this->getRuleConditionValue($ids->getBytes('condition.orderAffiliateCode'));
        static::assertEquals('!=', $ruleCondition['operator']);
        static::assertCount(1, $ruleCondition['affiliateCode']);
        static::assertContains('foo', $ruleCondition['affiliateCode']);

        $ruleCondition = $this->getRuleConditionValue($ids->getBytes('condition.customerAffiliateCode'));
        static::assertEquals('=', $ruleCondition['operator']);
        static::assertCount(1, $ruleCondition['affiliateCode']);
        static::assertContains('bar', $ruleCondition['affiliateCode']);

        $ruleCondition = $this->getRuleConditionValue($ids->getBytes('condition.orderAffiliateCode.empty'));
        static::assertEquals('empty', $ruleCondition['operator']);
        static::assertArrayNotHasKey('affiliateCode', $ruleCondition);

        $ruleCondition = $this->getRuleConditionValue($ids->getBytes('condition.customerAffiliateCode.empty'));
        static::assertEquals('empty', $ruleCondition['operator']);
        static::assertArrayNotHasKey('affiliateCode', $ruleCondition);

        $ruleCondition = $this->getRuleConditionValue($ids->getBytes('condition.orderCampaignCode'));
        static::assertEquals('!=', $ruleCondition['operator']);
        static::assertCount(1, $ruleCondition['campaignCode']);
        static::assertContains('baz', $ruleCondition['campaignCode']);

        $ruleCondition = $this->getRuleConditionValue($ids->getBytes('condition.customerCampaignCode'));
        static::assertEquals('=', $ruleCondition['operator']);
        static::assertCount(1, $ruleCondition['campaignCode']);
        static::assertContains('asd', $ruleCondition['campaignCode']);

        $ruleCondition = $this->getRuleConditionValue($ids->getBytes('condition.orderCampaignCode.empty'));
        static::assertEquals('empty', $ruleCondition['operator']);
        static::assertArrayNotHasKey('campaignCode', $ruleCondition);

        $ruleCondition = $this->getRuleConditionValue($ids->getBytes('condition.customerCampaignCode.empty'));
        static::assertEquals('empty', $ruleCondition['operator']);
        static::assertArrayNotHasKey('campaignCode', $ruleCondition);

        $this->cleanupDatabase($ids);
    }

    /**
     * @return array<string|int, mixed>
     */
    private function getRule(string $id): false|array
    {
        return $this->connection->fetchAssociative('SELECT * FROM rule WHERE id = :id', [
            'id' => $id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getRuleConditionValue(string $id): array
    {
        $value = $this->connection->fetchOne('SELECT `value` FROM rule_condition WHERE id = :id', [
            'id' => $id,
        ]);
        static::assertIsString($value);

        return json_decode((string) $value, true, 512, \JSON_THROW_ON_ERROR);
    }

    private function cleanupDatabase(IdsCollection $ids): void
    {
        $ruleConditionKeys = [
            'condition.orderAffiliateCode',
            'condition.customerAffiliateCode',
            'condition.orderAffiliateCode.empty',
            'condition.customerAffiliateCode.empty',
            'condition.orderCampaignCode',
            'condition.customerCampaignCode',
            'condition.orderCampaignCode.empty',
            'condition.customerCampaignCode.empty',
        ];
        foreach ($ids->getIdArray($ruleConditionKeys, true) as $ruleConditionIdArray) {
            $this->connection->delete('rule_condition', $ruleConditionIdArray);
        }

        foreach ($ids->getIdArray(['rule.1', 'rule.2'], true) as $ruleIdArray) {
            $this->connection->delete('rule', $ruleIdArray);
        }
    }
}
