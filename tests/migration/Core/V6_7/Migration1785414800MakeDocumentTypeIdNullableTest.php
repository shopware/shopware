<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1785414800MakeDocumentTypeIdNullable;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1785414800MakeDocumentTypeIdNullable::class)]
class Migration1785414800MakeDocumentTypeIdNullableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testDocumentTypeIdBecomesNullable(): void
    {
        $migration = new Migration1785414800MakeDocumentTypeIdNullable();
        $migration->update($this->connection);
        $migration->update($this->connection);

        /** @var array{Null: string} $column */
        $column = $this->connection->fetchAssociative(
            'SHOW COLUMNS FROM `document` WHERE `Field` = :field',
            ['field' => 'document_type_id'],
        );

        static::assertSame('YES', $column['Null']);
    }
}
