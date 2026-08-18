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
    private const FOREIGN_KEY_NAME = 'fk.document.referenced_document_id';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1773322284, (new Migration1773322284ChangeDocumentReferencedDocumentIdConstraint())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->resetReferencedDocumentForeignKey();

        $foreignKeyBefore = TableHelper::getForeignKeyOfTable(
            $this->connection,
            DocumentDefinition::ENTITY_NAME,
            self::FOREIGN_KEY_NAME
        );
        static::assertSame(ReferentialAction::RESTRICT->value, $foreignKeyBefore->onDeleteAction);

        $migration = new Migration1773322284ChangeDocumentReferencedDocumentIdConstraint();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $foreignKeyAfter = TableHelper::getForeignKeyOfTable(
            $this->connection,
            DocumentDefinition::ENTITY_NAME,
            self::FOREIGN_KEY_NAME
        );
        static::assertSame(ReferentialAction::SET_NULL->value, $foreignKeyAfter->onDeleteAction);
    }

    private function resetReferencedDocumentForeignKey(): void
    {
        $foreignKey = TableHelper::getForeignKeyOfTable(
            $this->connection,
            DocumentDefinition::ENTITY_NAME,
            self::FOREIGN_KEY_NAME
        );

        if ($foreignKey->onDeleteAction !== ReferentialAction::RESTRICT->value) {
            $this->connection->executeStatement(\sprintf('
                ALTER TABLE `document`
                DROP FOREIGN KEY `%s`;
            ', self::FOREIGN_KEY_NAME));

            $this->connection->executeStatement(\sprintf('
                ALTER TABLE `document`
                ADD CONSTRAINT `%s`
                FOREIGN KEY (`referenced_document_id`)
                REFERENCES `document` (`id`)
                ON DELETE RESTRICT ON UPDATE CASCADE;
            ', self::FOREIGN_KEY_NAME));
        }
    }
}
