<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class MigrationNameAndTimestampTest extends TestCase
{
    use KernelTestBehaviour;

    public function testMigrationNameAndTimestamp(): void
    {
        $migrations = $this->getContainer()->get(MigrationCollection::class)->getMigrationCollection();

        /** @var MigrationStep $migration */
        foreach ($migrations as $className => $migration) {
            $matches = [];
            $result = preg_match('/\\\\(?<name>Migration(?<timestamp>\d+)\w+)$/', $className, $matches);

            static::assertEquals(1, $result, sprintf(
                'Invalid migration name "%s". Example for a valid format: Migration1536232684Order',
                $className
            ));

            $timestamp = (int) $matches['timestamp'];
            static::assertEquals($migration->getCreationTimestamp(), $timestamp, sprintf(
                'Timestamp in migration name "%s" does not match timestamp of method "getCreationTimestamp"',
                $className
            ));
        }
    }
}
