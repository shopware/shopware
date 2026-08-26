<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Command;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Command\DeleteAdminFilesAfterBuildCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Runs the real command against a fabricated administration tree. Empty and unreadable
 * directories cannot ship as committed fixtures, so the real filesystem semantics live here;
 * the unit test covers the deletion routing with a filesystem double.
 *
 * @internal
 */
#[Package('framework')]
class DeleteAdminFilesAfterBuildCommandTest extends TestCase
{
    private string $adminDir;

    private Filesystem $filesystem;

    /**
     * @var list<string>
     */
    private array $lockedDirectories = [];

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->adminDir = sys_get_temp_dir() . '/admin-delete-files-test-' . Uuid::randomHex();
    }

    protected function tearDown(): void
    {
        foreach ($this->lockedDirectories as $lockedDirectory) {
            $this->filesystem->chmod($lockedDirectory, 0o755);
        }
        $this->lockedDirectories = [];

        $this->filesystem->remove($this->adminDir);
    }

    #[TestDox('The cleanup prunes build sources, keeps module translations, and preserves snippet directories')]
    public function testCleanupOfAFabricatedAdministrationTree(): void
    {
        $app = $this->adminDir . '/Resources/app/administration';

        // module: only the de-DE and en-GB translations survive, emptied directories are pruned
        $this->write($app . '/src/module/sw-example/snippet/de-DE.json');
        $this->write($app . '/src/module/sw-example/snippet/en-GB.json');
        $this->write($app . '/src/module/sw-example/snippet/fr-FR.json');
        $this->write($app . '/src/module/sw-example/index.js');
        $this->filesystem->mkdir($app . '/src/module/sw-empty/nested/deeper');

        // a removed root with a snippet directory inside keeps that directory and its ancestors
        $this->write($app . '/src/app/component/base/sw-thing/index.js');
        $this->write($app . '/src/app/component/base/sw-thing/snippet/en-GB.json');

        // roots without snippet survivors disappear entirely
        $this->write($app . '/src/app/adapter/view.js');
        $this->write($app . '/src/core/factory.js');
        $this->write($app . '/static/logo.png');
        $this->write($app . '/package-lock.json');

        $this->runCommand();

        static::assertFileExists($app . '/src/module/sw-example/snippet/de-DE.json');
        static::assertFileExists($app . '/src/module/sw-example/snippet/en-GB.json');
        static::assertFileDoesNotExist($app . '/src/module/sw-example/snippet/fr-FR.json');
        static::assertFileDoesNotExist($app . '/src/module/sw-example/index.js');
        static::assertDirectoryDoesNotExist($app . '/src/module/sw-empty');

        static::assertFileExists($app . '/src/app/component/base/sw-thing/snippet/en-GB.json');
        static::assertFileDoesNotExist($app . '/src/app/component/base/sw-thing/index.js');

        static::assertDirectoryDoesNotExist($app . '/src/app/adapter');
        static::assertDirectoryDoesNotExist($app . '/src/core');
        static::assertDirectoryDoesNotExist($app . '/static');
        static::assertFileDoesNotExist($app . '/package-lock.json');
    }

    #[TestDox('An unreadable directory is skipped without aborting the cleanup')]
    public function testUnreadableDirectoryIsSkipped(): void
    {
        if (\function_exists('posix_geteuid') && posix_geteuid() === 0) {
            static::markTestSkipped('Directory permissions do not apply to root.');
        }

        $app = $this->adminDir . '/Resources/app/administration';

        $this->write($app . '/src/module/sw-example/snippet/en-GB.json');
        $this->write($app . '/src/core/locked/secret.js');
        $this->write($app . '/src/app/adapter/view.js');

        $this->filesystem->chmod($app . '/src/core/locked', 0o000);
        $this->lockedDirectories[] = $app . '/src/core/locked';

        $this->runCommand();

        static::assertDirectoryExists($app . '/src/core/locked');
        static::assertDirectoryDoesNotExist($app . '/src/app/adapter');
    }

    private function runCommand(): void
    {
        $commandTester = new CommandTester(new DeleteAdminFilesAfterBuildCommand($this->filesystem, $this->adminDir));
        $commandTester->setInputs(['yes']);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
    }

    private function write(string $path): void
    {
        $this->filesystem->dumpFile($path, '{}');
    }
}
