<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1653376989ResetDefaultAlwaysValidConditionValue;

/**
 * @internal
 */
class Migration1653376989ResetDefaultAlwaysValidConditionValueTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testResetDefaultValwaysValidConditionValue(): void
    {
        $idCondition = $this->createAlwaysValidRule();

        $migration = new Migration1653376989ResetDefaultAlwaysValidConditionValue();
        $migration->update($this->connection);

        $value = $this->connection->fetchOne(
            'SELECT `value` FROM `rule_condition` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($idCondition)]
        );

        static::assertNull($value);
    }

    private function createAlwaysValidRule(): string
    {
        $idRule = Uuid::randomHex();
        $idCondition = Uuid::randomHex();

        $this->connection->insert('rule', [
            'id' => Uuid::fromHexToBytes($idRule),
            'name' => 'Always valid (Default)',
            'description' => null,
            'priority' => 100,
            'invalid' => 0,
            'module_types' => null,
            'custom_fields' => null,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'updated_at' => null,
        ]);
        $this->connection->insert('rule_condition', [
            'id' => Uuid::fromHexToBytes($idCondition),
            'type' => 'alwaysValid',
            'rule_id' => Uuid::fromHexToBytes($idRule),
            'parent_id' => null,
            'value' => '{"isAlwaysValid": true}',
            'position' => 0,
            'custom_fields' => null,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'updated_at' => null,
        ]);

        return $idCondition;
    }
}
