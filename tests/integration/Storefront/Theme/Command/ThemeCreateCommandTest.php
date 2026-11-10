<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\Command\ThemeCreateCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The duplicate check looks at the real project tree, so it runs against a disposable one here;
 * the scaffolding itself is covered by the unit test with a filesystem double.
 *
 * @internal
 */
#[Package('discovery')]
class ThemeCreateCommandTest extends TestCase
{
    private string $projectDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->projectDir = sys_get_temp_dir() . '/' . uniqid('theme-create-', true);
        $this->filesystem->mkdir($this->projectDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDir);
    }

    public function testCommandFailsOnDuplicate(): void
    {
        $commandTester = new CommandTester(new ThemeCreateCommand($this->projectDir, $this->filesystem));

        $commandTester->execute(['theme-name' => 'TestPlugin']);

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('Creating theme structure under', $commandTester->getDisplay(true));
        static::assertFileExists($this->projectDir . '/custom/plugins/TestPlugin/src/Resources/theme.json');

        $commandTester->execute(['theme-name' => 'TestPlugin']);

        $result = preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true)));
        static::assertIsString($result);
        static::assertStringContainsString('already exists', $result);
        static::assertSame(Command::FAILURE, $commandTester->getStatusCode());
    }
}
