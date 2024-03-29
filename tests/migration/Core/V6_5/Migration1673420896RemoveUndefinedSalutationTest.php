<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_5\Migration1673420896RemoveUndefinedSalutation;

/**
 * @package core
 *
 * @internal
 */
#[CoversClass(Migration1673420896RemoveUndefinedSalutation::class)]
class Migration1673420896RemoveUndefinedSalutationTest extends TestCase
{
    private const ASSOCIATION_TABLES = [
        'customer_address',
        'customer',
        'order_customer',
        'order_address',
        'newsletter_recipient',
    ];

    public function testDeleteUndefinedSalutation(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $undefinedSalutationId = $this->insertSalutation($connection, 'undefined');

        $migration = new Migration1673420896RemoveUndefinedSalutation();
        $migration->update($connection);
        // test multiple execution
        $migration->update($connection);

        $result = $connection->fetchOne('SELECT `id` FROM `salutation` WHERE `id` = :id', ['id' => $undefinedSalutationId]);

        static::assertFalse($result);
    }

    public function testForeignKeysSetNullOnDelete(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1673420896RemoveUndefinedSalutation();
        $migration->update($connection);
        // test multiple execution
        $migration->update($connection);

        foreach (self::ASSOCIATION_TABLES as $table) {
            $foreignKey = $this->getForeignKey($connection, $table);

            static::assertInstanceOf(ForeignKeyConstraint::class, $foreignKey);
            static::assertEquals('SET NULL', $foreignKey->onDelete());
        }
    }

    private function insertSalutation(Connection $connection, string $salutationKey): string
    {
        $id = Uuid::randomBytes();
        $connection->insert('salutation', [
            'id' => $id,
            'salutation_key' => $salutationKey,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $id;
    }

    private function getForeignKey(Connection $connection, string $relationTable): ?ForeignKeyConstraint
    {
        $foreignKeys = $connection->createSchemaManager()->listTableForeignKeys($relationTable);

        foreach ($foreignKeys as $foreignKey) {
            if (!$foreignKey instanceof ForeignKeyConstraint) {
                continue;
            }

            if ($foreignKey->getForeignTableName() === 'salutation' && $foreignKey->getLocalColumns() === ['salutation_id']) {
                return $foreignKey;
            }
        }

        return null;
    }
}
