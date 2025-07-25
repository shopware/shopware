<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\System\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\System\Command\SyncComposerVersionCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(SyncComposerVersionCommand::class)]
class SyncComposerVersionCommandTest extends TestCase
{
    private string $projectDir = '';

    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/' . uniqid('shopware-sync-composer-version-test', true);
        $this->fs = new Filesystem();

        $this->fs->mkdir($this->projectDir);
        $this->fs->dumpFile($this->projectDir . '/composer.json', json_encode([
            'require' => [
                'symfony/symfony' => '5.3.0',
                'foo/bar' => '1.0.0',
                'test/package' => '1.0.0',
            ],
        ], \JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->projectDir);
    }

    public function testSync(): void
    {
        $this->fs->dumpFile($this->projectDir . '/src/Bundle2/composer.json', json_encode([
            'require' => [
                'foo/bar' => '1.0.0',
            ],
        ], \JSON_THROW_ON_ERROR));

        $this->fs->dumpFile($this->projectDir . '/src/Bundle1/composer.json', json_encode([
            'require' => [
                'symfony/symfony' => '5.2.0',
                'foo/bar' => '1.0.0',
                'test/package' => '1.0.0',
            ],
        ], \JSON_THROW_ON_ERROR));

        $tester = new CommandTester(new SyncComposerVersionCommand($this->projectDir, $this->fs));
        $tester->execute([]);

        $bundle1Json = json_decode($this->fs->readFile($this->projectDir . '/src/Bundle1/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('5.3.0', $bundle1Json['require']['symfony/symfony']);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $this->getOutput($tester);
        static::assertStringContainsString('Updating composer.json of "Bundle1" bundle', $output);
        static::assertStringNotContainsString('Updating composer.json of "Bundle2" bundle', $output);
        static::assertStringContainsString('Composer dependencies of bundles synced with the root composer.json file', $output);
    }

    public function testAlreadySynced(): void
    {
        $this->fs->dumpFile($this->projectDir . '/src/Bundle1/composer.json', json_encode([
            'require' => [
                'symfony/symfony' => '5.3.0',
                'foo/bar' => '1.0.0',
                'test/package' => '1.0.0',
            ],
        ], \JSON_THROW_ON_ERROR));

        $tester = new CommandTester(new SyncComposerVersionCommand($this->projectDir, $this->fs));
        $tester->execute([]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $this->getOutput($tester);
        static::assertStringContainsString('Composer dependencies of bundles are already in sync with the root composer.json file.', $output);
    }

    public function testDryRun(): void
    {
        $this->fs->dumpFile($this->projectDir . '/src/Bundle1/composer.json', json_encode([
            'require' => [
                'symfony/symfony' => '5.2.0',
                'foo/bar' => '1.0.0',
                'test/package' => '1.0.0',
            ],
        ], \JSON_THROW_ON_ERROR));
        $command = new SyncComposerVersionCommand($this->projectDir, $this->fs);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $bundle1Json = json_decode($this->fs->readFile($this->projectDir . '/src/Bundle1/composer.json'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('5.2.0', $bundle1Json['require']['symfony/symfony']);
        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        $output = $this->getOutput($tester);
        static::assertStringContainsString('Running in dry-run mode: no files will be changed', $output);
        static::assertStringContainsString('Composer dependencies of bundles are not in sync with the root composer.json file.', $output);
        static::assertStringContainsString('Please run the `sync:composer:version` command without the --dry-run option to sync them.', $output);
    }

    public function testPackageInRootButNotInBundle(): void
    {
        $this->fs->dumpFile($this->projectDir . '/src/Bundle1/composer.json', json_encode([
            'require' => [
                'foo/bar' => '1.0.0',
                'shopware/core' => '*',
            ],
        ], \JSON_THROW_ON_ERROR));

        $this->fs->dumpFile($this->projectDir . '/src/Bundle2/composer.json', json_encode([
            'require' => [
                'symfony/symfony' => '5.3.0',
                'shopware/core' => '*',
            ],
        ], \JSON_THROW_ON_ERROR));

        $tester = new CommandTester(new SyncComposerVersionCommand($this->projectDir, $this->fs));
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
        $this->fs->dumpFile($this->projectDir . '/src/Bundle1/composer.json', json_encode([
            'require' => [
                'symfony/symfony' => '5.3.0',
                'foo/bar' => '1.0.0',
                'test/package' => '1.0.0',
                'not/in-root' => '1.0.0',
            ],
        ], \JSON_THROW_ON_ERROR));

        $tester = new CommandTester(new SyncComposerVersionCommand($this->projectDir, $this->fs));
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
}
