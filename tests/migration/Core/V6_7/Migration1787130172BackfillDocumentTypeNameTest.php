<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1787130172BackfillDocumentTypeName;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1787130172BackfillDocumentTypeName::class)]
class Migration1787130172BackfillDocumentTypeNameTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testUpdateBackfillsTypeNameFromDocumentTypeId(): void
    {
        $configId = $this->connection->fetchOne('SELECT `id` FROM `document_base_config` WHERE `document_type_id` IS NOT NULL LIMIT 1');
        static::assertIsString($configId);

        $expected = $this->connection->fetchOne(
            'SELECT `type`.`technical_name`
             FROM `document_base_config` AS `config`
             INNER JOIN `document_type` AS `type` ON `type`.`id` = `config`.`document_type_id`
             WHERE `config`.`id` = :id',
            ['id' => $configId]
        );
        static::assertIsString($expected);

        $this->connection->executeStatement('UPDATE `document_base_config` SET `type_name` = NULL WHERE `id` = :id', ['id' => $configId]);

        $migration = new Migration1787130172BackfillDocumentTypeName();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $actual = $this->connection->fetchOne('SELECT `type_name` FROM `document_base_config` WHERE `id` = :id', ['id' => $configId]);
        static::assertSame($expected, $actual);
    }
}
