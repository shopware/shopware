<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(MigrationCollection::class)]
class MigrationNameAndTimestampTest extends TestCase
{
    use KernelTestBehaviour;

    public function testMigrationNameAndTimestampAreNamedAfterOptionalConvention(): void
    {
        $loader = $this->getContainer()->get(MigrationCollectionLoader::class);
        $migrationCollection = $loader->collectAll();

        foreach ($migrationCollection as $migrations) {
            foreach ($migrations->getMigrationSteps() as $className => $migration) {
                $matches = [];
                $result = preg_match('/\\\\(?<name>Migration(?<timestamp>\d+)\w+)$/', (string) $className, $matches);

                static::assertSame(1, $result, \sprintf(
                    'Invalid migration name "%s". Example for a valid format: Migration1536232684Order',
                    $className
                ));

                $timestamp = (int) ($matches['timestamp'] ?? 0);
                static::assertSame($migration->getCreationTimestamp(), $timestamp, \sprintf(
                    'Timestamp in migration name "%s" does not match timestamp of method "getCreationTimestamp"',
                    $className
                ));
            }
        }
    }
}
