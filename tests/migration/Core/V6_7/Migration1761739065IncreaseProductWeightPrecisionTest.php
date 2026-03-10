<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_7\Migration1761739065IncreaseProductWeightPrecision;

/**
 * @internal
 */
#[CoversClass(Migration1761739065IncreaseProductWeightPrecision::class)]
class Migration1761739065IncreaseProductWeightPrecisionTest extends TestCase
{
    use KernelTestBehaviour;

    public function testUpdateIncreasesPrecision(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        try {
            $this->setProductWeightPrecision($connection, 'DECIMAL(10,3) UNSIGNED NULL');

            $migration = new Migration1761739065IncreaseProductWeightPrecision();
            $migration->update($connection);
            $migration->update($connection);

            static::assertSame(
                'decimal(15,6) unsigned',
                $this->getProductWeightColumnType($connection)
            );
        } finally {
            $this->setProductWeightPrecision($connection, 'DECIMAL(15,6) UNSIGNED NULL');
        }
    }

    public function testUpdateSkipsWhenPrecisionAlreadyExtended(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        try {
            $this->setProductWeightPrecision($connection, 'DECIMAL(20,8) UNSIGNED NULL');

            $migration = new Migration1761739065IncreaseProductWeightPrecision();
            $migration->update($connection);

            static::assertSame(
                'decimal(20,8) unsigned',
                $this->getProductWeightColumnType($connection)
            );
        } finally {
            $this->setProductWeightPrecision($connection, 'DECIMAL(15,6) UNSIGNED NULL');
        }
    }

    private function setProductWeightPrecision(Connection $connection, string $definition): void
    {
        $connection->executeStatement(
            \sprintf('ALTER TABLE `product` MODIFY `weight` %s', $definition)
        );
    }

    private function getProductWeightColumnType(Connection $connection): string
    {
        $columnTypeQuery = <<<'SQL'
            SELECT LOWER(COLUMN_TYPE)
            FROM information_schema.columns
            WHERE table_schema = :schema
              AND table_name = 'product'
              AND column_name = 'weight';
        SQL;

        $type = $connection->fetchOne($columnTypeQuery, ['schema' => $connection->getDatabase()]);

        return \is_string($type) ? $type : '';
    }
}
