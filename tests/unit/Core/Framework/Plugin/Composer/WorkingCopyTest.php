<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Composer;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Composer\Message\CleanupOldVendorDirectories;
use Shopware\Core\Framework\Plugin\Composer\WorkingCopy;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Envelope;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkingCopy::class)]
class WorkingCopyTest extends TestCase
{
    private Filesystem $filesystem;

    private string $projectDir;

    private LockFactory $lockFactory;

    private CollectingMessageBus $messageBus;

    private WorkingCopy $workingCopy;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->projectDir = sys_get_temp_dir() . '/working-copy-' . bin2hex(random_bytes(6));
        $this->filesystem->dumpFile($this->projectDir . '/composer.json', '{"name": "live"}');
        $this->filesystem->dumpFile($this->projectDir . '/composer.lock', '{"packages": "live"}');
        $this->filesystem->dumpFile($this->projectDir . '/vendor/autoload.php', 'live');
        $this->filesystem->dumpFile($this->projectDir . '/vendor/composer/installed.php', 'live');

        $this->lockFactory = new LockFactory(new InMemoryStore());
        $this->messageBus = new CollectingMessageBus();
        $this->workingCopy = new WorkingCopy($this->projectDir, $this->filesystem, $this->lockFactory, $this->messageBus);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDir);
    }

    public function testSuccessfulOperationReplacesVendorComposerJsonAndLock(): void
    {
        $exitCode = $this->workingCopy->run(function (string $composerJson): int {
            $this->filesystem->dumpFile($composerJson, '{"name": "staged"}');
            $this->filesystem->dumpFile(substr($composerJson, 0, -4) . 'lock', '{"packages": "staged"}');
            $this->filesystem->dumpFile(getenv('COMPOSER_VENDOR_DIR') . '/autoload.php', 'staged');

            return 0;
        });

        static::assertSame(0, $exitCode);
        static::assertStringEqualsFile($this->projectDir . '/composer.json', '{"name": "staged"}');
        static::assertStringEqualsFile($this->projectDir . '/composer.lock', '{"packages": "staged"}');
        static::assertStringEqualsFile($this->projectDir . '/vendor/autoload.php', 'staged');
        static::assertSame(['.vendor-old-'], $this->siblingPrefixes());
        static::assertEquals([new Envelope(new CleanupOldVendorDirectories())], $this->messageBus->getMessages());
    }

    public function testOperationRunsAgainstACopyOfTheLiveProject(): void
    {
        $this->workingCopy->run(static function (string $composerJson): int {
            static::assertStringEqualsFile($composerJson, '{"name": "live"}');
            static::assertSame($composerJson, getenv('COMPOSER'));
            static::assertSame($composerJson, $_SERVER['COMPOSER'] ?? null);
            static::assertStringEqualsFile(substr($composerJson, 0, -4) . 'lock', '{"packages": "live"}');
            static::assertStringEqualsFile(getenv('COMPOSER_VENDOR_DIR') . '/autoload.php', 'live');

            return 0;
        });

        static::assertFalse(getenv('COMPOSER'));
        static::assertArrayNotHasKey('COMPOSER', $_SERVER);
        static::assertFalse(getenv('COMPOSER_VENDOR_DIR'));
        static::assertArrayNotHasKey('COMPOSER_VENDOR_DIR', $_ENV);
    }

    public function testRelativeSymlinksOfPathRepositoriesStayValid(): void
    {
        $this->filesystem->dumpFile($this->projectDir . '/custom/plugins/SwagTest/composer.json', '{"name": "swag/test"}');
        $this->filesystem->symlink('../../custom/plugins/SwagTest', $this->projectDir . '/vendor/swag/test');

        $this->workingCopy->run(static fn (): int => 0);

        static::assertSame('../../custom/plugins/SwagTest', readlink($this->projectDir . '/vendor/swag/test'));
        static::assertFileExists($this->projectDir . '/vendor/swag/test/composer.json');
    }

    public function testClassLoadersComposerRegisteredForTheWorkingCopyAreRemoved(): void
    {
        $stagingDir = null;
        $this->workingCopy->run(static function () use (&$stagingDir): int {
            $stagingDir = (string) getenv('COMPOSER_VENDOR_DIR');
            (new ClassLoader($stagingDir))->register();

            return 0;
        });

        static::assertNotNull($stagingDir);
        static::assertArrayNotHasKey($stagingDir, ClassLoader::getRegisteredLoaders());
    }

    public function testFailedOperationLeavesTheLiveProjectUntouched(): void
    {
        $exitCode = $this->workingCopy->run(function (string $composerJson): int {
            $this->filesystem->dumpFile($composerJson, '{"name": "staged"}');
            $this->filesystem->remove((string) getenv('COMPOSER_VENDOR_DIR') . '/autoload.php');

            return 2;
        });

        static::assertSame(2, $exitCode);
        $this->assertLiveProjectIsUntouched();
        static::assertSame([], $this->messageBus->getMessages());
    }

    public function testOperationThatLeavesNoAutoloaderIsNotSwappedIn(): void
    {
        $this->expectExceptionObject(PluginException::composerWorkingCopyNotPossible('Composer did not write "autoload.php" into the working copy'));

        try {
            $this->workingCopy->run(function (): int {
                $this->filesystem->remove((string) getenv('COMPOSER_VENDOR_DIR') . '/autoload.php');

                return 0;
            });
        } finally {
            $this->assertLiveProjectIsUntouched();
        }
    }

    public function testLeftoversOfEarlierOperationsAreRemovedFirst(): void
    {
        $this->filesystem->mkdir($this->projectDir . '/.vendor-staging-crashed');
        $this->filesystem->mkdir($this->projectDir . '/.vendor-old-replaced');
        $this->filesystem->dumpFile($this->projectDir . '/composer.staging-crashed.json', '{}');

        $this->workingCopy->run(static fn (): int => 1);

        static::assertSame([], $this->siblingPrefixes());
        static::assertFileDoesNotExist($this->projectDir . '/composer.staging-crashed.json');
    }

    public function testRefusesWhileAnotherOperationRuns(): void
    {
        $lock = $this->lockFactory->createLock(WorkingCopy::LOCK_KEY);
        $lock->acquire();

        $this->expectExceptionObject(PluginException::composerOperationInProgress());

        $this->workingCopy->run(static fn (): int => 0);
    }

    public function testRefusesAfterAnInterruptedReplacement(): void
    {
        $this->filesystem->dumpFile($this->projectDir . '/.composer-swap-in-progress', 'crashed');

        $this->expectExceptionObject(PluginException::composerWorkingCopyInterrupted($this->projectDir . '/.composer-swap-in-progress'));

        $this->workingCopy->run(static fn (): int => 0);
    }

    public function testRefusesACustomVendorDirectory(): void
    {
        $this->filesystem->dumpFile($this->projectDir . '/composer.json', '{"config": {"vendor-dir": "lib"}}');

        $this->expectExceptionObject(PluginException::composerWorkingCopyNotPossible('composer.json configures the vendor directory "lib"'));

        $this->workingCopy->run(static fn (): int => 0);
    }

    public function testRefusesASymlinkedVendorDirectory(): void
    {
        $this->filesystem->rename($this->projectDir . '/vendor', $this->projectDir . '/shared-vendor');
        $this->filesystem->symlink($this->projectDir . '/shared-vendor', $this->projectDir . '/vendor');

        $this->expectExceptionObject(PluginException::composerWorkingCopyNotPossible(\sprintf('"%s/vendor" is not a directory', $this->projectDir)));

        $this->workingCopy->run(static fn (): int => 0);
    }

    public function testRemoveOldVendorDirectories(): void
    {
        $this->filesystem->mkdir($this->projectDir . '/.vendor-old-replaced');
        $this->filesystem->mkdir($this->projectDir . '/.vendor-staging-running');

        $this->workingCopy->removeOldVendorDirectories();

        static::assertSame(['.vendor-staging-'], $this->siblingPrefixes());
    }

    public function testRemoveOldVendorDirectoriesWaitsForARunningOperation(): void
    {
        $this->filesystem->mkdir($this->projectDir . '/.vendor-old-replaced');
        $lock = $this->lockFactory->createLock(WorkingCopy::LOCK_KEY);
        $lock->acquire();

        $this->workingCopy->removeOldVendorDirectories();

        static::assertSame(['.vendor-old-'], $this->siblingPrefixes());
    }

    private function assertLiveProjectIsUntouched(): void
    {
        static::assertStringEqualsFile($this->projectDir . '/composer.json', '{"name": "live"}');
        static::assertStringEqualsFile($this->projectDir . '/composer.lock', '{"packages": "live"}');
        static::assertStringEqualsFile($this->projectDir . '/vendor/autoload.php', 'live');
        static::assertSame([], $this->siblingPrefixes());
        static::assertSame([], glob($this->projectDir . '/composer.staging-*'));
    }

    /**
     * @return list<string>
     */
    private function siblingPrefixes(): array
    {
        $prefixes = [];
        foreach (glob($this->projectDir . '/.vendor-*', \GLOB_ONLYDIR) ?: [] as $directory) {
            $prefixes[] = substr(basename($directory), 0, (int) strrpos(basename($directory), '-') + 1);
        }

        sort($prefixes);

        return $prefixes;
    }
}
