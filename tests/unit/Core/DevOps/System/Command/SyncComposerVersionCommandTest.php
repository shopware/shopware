<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\System\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\System\Command\SyncComposerVersionCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\Framework\Util\InMemoryUtilFilesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SyncComposerVersionCommand::class)]
class SyncComposerVersionCommandTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/_fixtures/sync-composer-version';

    public function testSync(): void
    {
        $projectDir = self::FIXTURE_DIR . '/sync';
        $fs = $this->createProjectFilesystem($projectDir);

        $tester = new CommandTester(new SyncComposerVersionCommand($projectDir, $fs));
        $tester->execute([]);

        $bundle1Json = json_decode($fs->readFile($projectDir . '/src/Bundle1/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('5.3.0', $bundle1Json['require']['symfony/symfony']);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $this->getOutput($tester);
        static::assertStringContainsString('Updating composer.json of "Bundle1" bundle', $output);
        static::assertStringNotContainsString('Updating composer.json of "Bundle2" bundle', $output);
        static::assertStringContainsString('Composer dependencies of bundles synced with the root composer.json file', $output);
    }

    public function testAlreadySynced(): void
    {
        $projectDir = self::FIXTURE_DIR . '/already-synced';

        $tester = new CommandTester(new SyncComposerVersionCommand($projectDir, $this->createProjectFilesystem($projectDir)));
        $tester->execute([]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $this->getOutput($tester);
        static::assertStringContainsString('Composer dependencies of bundles are already in sync with the root composer.json file.', $output);
    }

    public function testDryRun(): void
    {
        $projectDir = self::FIXTURE_DIR . '/dry-run';
        $fs = $this->createProjectFilesystem($projectDir);
        $command = new SyncComposerVersionCommand($projectDir, $fs);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $bundle1Json = json_decode($fs->readFile($projectDir . '/src/Bundle1/composer.json'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('5.2.0', $bundle1Json['require']['symfony/symfony']);
        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        $output = $this->getOutput($tester);
        static::assertStringContainsString('Running in dry-run mode: no files will be changed', $output);
        static::assertStringContainsString('Composer dependencies of bundles are not in sync with the root composer.json file.', $output);
        static::assertStringContainsString('Please run the `sync:composer:version` command without the --dry-run option to sync them.', $output);
    }

    public function testPackageInRootButNotInBundle(): void
    {
        $projectDir = self::FIXTURE_DIR . '/package-in-root-but-not-in-bundle';

        $tester = new CommandTester(new SyncComposerVersionCommand($projectDir, $this->createProjectFilesystem($projectDir)));
        $tester->execute([]);

        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        $output = $this->getOutput($tester);
        static::assertStringContainsString('The following packages are defined in the root composer.json but not in the bundles:', $output);
        static::assertStringContainsString('- test/package', $output);
        static::assertStringNotContainsString('- foo/bar', $output);
        static::assertStringNotContainsString('- shopware/core', $output);
    }

    public function testPackageInBundleButNotInRoot(): void
    {
        $projectDir = self::FIXTURE_DIR . '/package-in-bundle-but-not-in-root';

        $tester = new CommandTester(new SyncComposerVersionCommand($projectDir, $this->createProjectFilesystem($projectDir)));
        $tester->execute([]);

        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        $output = $this->getOutput($tester);
        static::assertStringContainsString('The following packages are defined in the bundles but not in the root composer.json:', $output);
        static::assertStringContainsString('- "not/in-root" from bundles: Bundle1', $output);
    }

    private function getOutput(CommandTester $tester): string
    {
        $output = preg_replace('/\s+/', ' ', $tester->getDisplay(true));
        static::assertIsString($output);

        return $output;
    }

    /**
     * The command globs the bundle composer.json files on disk, so the in-memory filesystem is seeded with the fixture paths.
     */
    private function createProjectFilesystem(string $projectDir): InMemoryUtilFilesystem
    {
        $files = [];
        foreach ([$projectDir . '/composer.json', ...(glob($projectDir . '/src/*/composer.json') ?: [])] as $path) {
            $files[$path] = (string) file_get_contents($path);
        }

        return new InMemoryUtilFilesystem($files);
    }
}
