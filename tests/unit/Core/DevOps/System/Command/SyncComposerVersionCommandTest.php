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

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/' . uniqid('shopware-sync-composer-version-test', true);
        $fs = new Filesystem();

        $fs->mkdir($this->projectDir);
        $fs->dumpFile($this->projectDir . '/composer.json', json_encode([
            'require' => [
                'symfony/symfony' => '5.3.0',
            ],
        ], \JSON_THROW_ON_ERROR));

        $fs->dumpFile($this->projectDir . '/src/Bundle1/composer.json', json_encode([
            'require' => [
                'symfony/symfony' => '5.2.0',
            ],
        ], \JSON_THROW_ON_ERROR));

        $fs->dumpFile($this->projectDir . '/src/WebInstaller/composer.json', json_encode([
            'require' => [
                'symfony/symfony' => '5.2.0',
            ],
        ], \JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        $fs = new Filesystem();
        $fs->remove($this->projectDir);
    }

    public function testSync(): void
    {
        $command = new SyncComposerVersionCommand($this->projectDir);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $bundle1Json = json_decode((string) file_get_contents($this->projectDir . '/src/Bundle1/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
        static::assertEquals('5.3.0', $bundle1Json['require']['symfony/symfony']);

        $webInstaller = json_decode((string) file_get_contents($this->projectDir . '/src/WebInstaller/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
        static::assertEquals('5.2.0', $webInstaller['require']['symfony/symfony']);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testDryRun(): void
    {
        $command = new SyncComposerVersionCommand($this->projectDir);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $bundle1Json = json_decode((string) file_get_contents($this->projectDir . '/src/Bundle1/composer.json'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals('5.2.0', $bundle1Json['require']['symfony/symfony']);
        static::assertSame(Command::FAILURE, $tester->getStatusCode());
    }
}
