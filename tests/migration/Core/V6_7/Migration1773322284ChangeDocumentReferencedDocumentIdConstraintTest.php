<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1773322284ChangeDocumentReferencedDocumentIdConstraint;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1773322284ChangeDocumentReferencedDocumentIdConstraint::class)]
class Migration1773322284ChangeDocumentReferencedDocumentIdConstraintTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        if (TableHelper::foreignKeyExists(
            $this->connection,
            DocumentDefinition::ENTITY_NAME,
            'fk.document.referenced_document_id'
        )) {
            $this->connection->executeStatement('
                ALTER TABLE `document`
                DROP FOREIGN KEY `fk.document.referenced_document_id`;
            ');
        }

        $this->connection->executeStatement('
            ALTER TABLE `document`
            ADD CONSTRAINT `fk.document.referenced_document_id`
            FOREIGN  KEY (`referenced_document_id`)
            REFERENCES `document` (`id`)
            ON DELETE SET NULL ON UPDATE CASCADE;
        ');
    }

    public function testMigration(): void
    {
        $migration = new Migration1773322284ChangeDocumentReferencedDocumentIdConstraint();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $foreignKey = TableHelper::getForeignKeyOfTable(
            $this->connection,
            DocumentDefinition::ENTITY_NAME,
            'fk.document.referenced_document_id'
        );
        static::assertSame(ReferentialAction::SET_NULL->value, $foreignKey->onDeleteAction);
    }
}
