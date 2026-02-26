<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\Command\RefreshMigrationCommand;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Migration\MigrationStep;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[CoversClass(RefreshMigrationCommand::class)]
class RefreshMigrationCommandTest extends TestCase
{
    public function testExecuteThrowsWhenClassNameDoesNotContainMigrationTimestamp(): void
    {
        $command = new RefreshMigrationCommand();
        $commandTester = new CommandTester($command);

        $this->expectExceptionObject(MigrationException::couldNotDetermineTimestamp());
        $commandTester->execute(['path' => __DIR__ . '/_fixtures/InvalidMigration.php']);
    }

    public function testExecuteThrowsClassAtPathDoesNotExist(): void
    {
        $command = new RefreshMigrationCommand();
        $commandTester = new CommandTester($command);

        $this->expectExceptionObject(MigrationException::migrationFileDoesNotExist(__DIR__ . '/_fixtures/DoesNotExist.php'));
        $commandTester->execute(['path' => __DIR__ . '/_fixtures/DoesNotExist.php']);
    }

    public function testExecute(): void
    {
        $command = new RefreshMigrationCommand();
        $commandTester = new CommandTester($command);

        $fs = new Filesystem();

        $filePath = __DIR__ . '/_fixtures/Migration1772030791Test.php';
        // create temp file for test
        $fs->copy(__DIR__ . '/_fixtures/Migration1772030791Test.php.bak', $filePath);

        $commandTester->execute(['path' => $filePath]);

        $finder = (new Finder())
            ->in(__DIR__ . '/_fixtures')
            ->name('Migration*.php');

        static::assertCount(1, $finder);

        foreach ($finder as $file => $fileInfo) {
            static::assertFileExists($file);

            require_once $file;

            $class = $fileInfo->getBasename('.php');
            $migration = new $class();

            static::assertInstanceOf(MigrationStep::class, $migration);
            $newTimestamp = $migration->getCreationTimestamp();
            // assert that the new timestamp is within 3 second of the current time, to account for any slight delays in execution
            static::assertEqualsWithDelta(time(), $newTimestamp, 3);
            // assert that the new timestamp is in the file name as well
            static::assertStringContainsString((string) $newTimestamp, $file);

            $fs->remove($file);
        }
    }
}
