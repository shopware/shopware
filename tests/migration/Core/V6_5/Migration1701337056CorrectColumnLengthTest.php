<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_5\Migration1701337056CorrectColumnLength;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(Migration1701337056CorrectColumnLength::class)]
class Migration1701337056CorrectColumnLengthTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigrate(): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE `country_translation` MODIFY `address_format` VARCHAR(255) DEFAULT NULL;'
        );

        $type = $this->getFieldType($this->connection, 'country_translation', 'address_format');

        $m = new Migration1701337056CorrectColumnLength();
        $m->update($this->connection);

        $typeAfter = $this->getFieldType($this->connection, 'country_translation', 'address_format');

        static::assertNotEquals($type, $typeAfter);
    }

    private function getFieldType(Connection $connection, string $table, string $column): string
    {
        /** @var array{Type: string} $row */
        $row = $connection->fetchAssociative('SHOW COLUMNS FROM ' . $table . ' WHERE Field = ?', [$column]);

        return $row['Type'];
    }
}
