<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Migration\V6_3\Migration1604502151AddThemePreviewMediaConstraint;

/**
 * @internal
 *
 * @group skip-paratest
 */
class Migration1604502151AddThemePreviewMediaConstraintTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const FK_THEME_TABLE = 'theme';
    private const FK_THEME_COLUMN = 'preview_media_id';
    private const FK_MEDIA_TABLE = 'media';
    private const FK_MEDIA_COLUMN = 'id';

    private const FK_INDEX = 'fk.theme.preview_media_id';

    private Connection $connection;

    private Migration1604502151AddThemePreviewMediaConstraint $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);
        $this->migration = new Migration1604502151AddThemePreviewMediaConstraint();

        // Revert the foreign key insert
        $this->removeForeignKeyConstraintIfExists();
    }

    /**
     * Asserts the migration adds the new foreign key for preview_media_id.
     */
    public function testUpdate(): void
    {
        $this->connection->rollBack();
        $this->migration->update($this->connection);
        $this->connection->beginTransaction();

        $foreignKeyName = $this->getPreviewMediaForeignKeyName();

        static::assertIsString($foreignKeyName);
        static::assertNotEmpty($foreignKeyName);
    }

    /**
     * This is a regression test for the problem described in NEXT-12797.
     *
     * @testWith [1]
     *           [0]
     *           [3]
     *
     * @see https://issues.shopware.com/issues/NEXT-12797
     */
    public function testForeignKeySafeguardIsWorking(int $invalidEntries): void
    {
        $this->connection->rollBack();
        // Insert problematic DB entries
        $this->insertBogusReferenceIntoThemeTable($invalidEntries);

        $exception = null;

        try {
            $this->migration->update($this->connection);
        } catch (\Throwable $e) {
            $exception = $e;
        }

        // Expect the migration to have taken care of the invalid references
        static::assertNull($exception);

        $this->connection->delete(self::FK_THEME_TABLE, ['name' => self::class]);

        $this->connection->beginTransaction();
    }

    private function removeForeignKeyConstraintIfExists(): void
    {
        $foreignKeyName = $this->getPreviewMediaForeignKeyName();

        if ($foreignKeyName !== null) {
            $this->connection->rollBack();
            $this->connection->executeStatement(self::dropIndexAndForeignKeyQuery($foreignKeyName));
            $this->connection->beginTransaction();
        }
    }

    private function getPreviewMediaForeignKeyName(): ?string
    {
        $foreignKeyName = $this->connection->fetchOne(self::getForeignKeyQuery());

        if (\is_string($foreignKeyName) && !empty($foreignKeyName)) {
            return $foreignKeyName;
        }

        return null;
    }

    private function insertBogusReferenceIntoThemeTable(int $amount = 1): void
    {
        for ($i = 0; $i < $amount; ++$i) {
            $this->connection->insert(
                self::FK_THEME_TABLE,
                [
                    'id' => Uuid::randomBytes(),
                    'name' => self::class,
                    'author' => self::class,
                    'preview_media_id' => Uuid::randomBytes(),
                    'active' => 0,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }
    }

    private static function getForeignKeyQuery(): string
    {
        $template = <<<'EOF'
SELECT `CONSTRAINT_NAME`
FROM `information_schema`.`KEY_COLUMN_USAGE`
WHERE
    `TABLE_NAME` = '#local_table#' AND
    `REFERENCED_TABLE_NAME` = '#referenced_table#' AND
    `COLUMN_NAME` = '#local_column#' AND
    `REFERENCED_COLUMN_NAME` = '#referenced_column#';
EOF;

        return str_replace(
            ['#local_table#', '#referenced_table#', '#local_column#', '#referenced_column#'],
            [
                self::FK_THEME_TABLE,
                self::FK_MEDIA_TABLE,
                self::FK_THEME_COLUMN,
                self::FK_MEDIA_COLUMN,
            ],
            $template
        );
    }

    /**
     * The foreign key name (in contrast to the index name) is not explicitly set in the tested migration,
     * therefore it needs to be determined at runtime.
     */
    private static function dropIndexAndForeignKeyQuery(string $foreignKey): string
    {
        $template = <<<'EOF'
ALTER TABLE `#table#` DROP FOREIGN KEY `#key#`;
DROP INDEX `#index#` ON `#table#`;
EOF;

        return str_replace(
            ['#table#', '#index#', '#key#'],
            [
                self::FK_THEME_TABLE,
                self::FK_INDEX,
                $foreignKey,
            ],
            $template
        );
    }
}
