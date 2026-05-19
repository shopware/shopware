<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1756296414UpdateSnippetSetBaseFiles;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1756296414UpdateSnippetSetBaseFiles::class)]
class Migration1756296414UpdateSnippetSetBaseFilesTest extends TestCase
{
    public function testGetCreationTimestamp(): void
    {
        static::assertSame(
            1756296414,
            (new Migration1756296414UpdateSnippetSetBaseFiles())->getCreationTimestamp()
        );
    }

    public function testRenameSnippetSetBaseFiles(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1756296414UpdateSnippetSetBaseFiles();

        $this->revertMigration($connection);

        $sql = <<<'SQL'
            SELECT name, base_file
            FROM `snippet_set`
            WHERE `base_file` LIKE 'messages.%'
        SQL;

        for ($i = 0; $i < 2; ++$i) {
            $migration->update($connection);
            $result = $connection->fetchAllAssociative($sql);

            static::assertCount(2, $result);
            static::assertNotContains('messages.de-DE', array_column($result, 'base_file'));
            static::assertContains('messages.de', array_column($result, 'base_file'));
            static::assertNotContains('messages.en-GB', array_column($result, 'base_file'));
            static::assertContains('messages.en', array_column($result, 'base_file'));
        }
    }

    public function revertMigration(Connection $connection): void
    {
        $revertStatement = <<<'SQL'
            UPDATE `snippet_set`
            SET base_file = REPLACE(REPLACE(`base_file`, 'de', 'de-DE'), 'en', 'en-GB')
            WHERE `base_file` IN ('messages.de', 'messages.en')
        SQL;

        $connection->executeStatement($revertStatement);
    }
}
