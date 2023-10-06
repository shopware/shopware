<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Storefront\Theme\Command\ThemeCreateCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
class ThemeCreateCommandTest extends TestCase
{
    use KernelTestBehaviour;

    private const THEME_NAME = 'TestPlugin';

    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = $this->getContainer()->getParameter('kernel.project_dir');
    }

    protected function tearDown(): void
    {
        $this->removeTheme(self::THEME_NAME);
    }

    public function testSuccessfulCreateCommand(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute(['theme-name' => self::THEME_NAME]);

        static::assertStringContainsString('Creating theme structure under', preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true))));
    }

    public function testCommandFailsOnDuplicate(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute(['theme-name' => self::THEME_NAME]);

        static::assertStringContainsString('Creating theme structure under', $commandTester->getDisplay(true));

        $commandTester->execute(['theme-name' => self::THEME_NAME]);

        static::assertStringContainsString(self::THEME_NAME . ' already exists', preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true))));
    }

    /**
     * @dataProvider commandFailsWithWrongNameDataProvider
     */
    public function testCommandFailsWithWrongName(string $name, string $expectedMessage): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute(['theme-name' => $name]);

        static::assertStringContainsString($expectedMessage, preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true))));
    }

    public static function commandFailsWithWrongNameDataProvider(): array
    {
        return [
            ['name' => 'abc', 'expectedMessage' => 'The name must start with an uppercase character'],
            ['name' => 'Abc', 'expectedMessage' => 'Theme name is too short (min 4 characters), contains invalid characters'],
            ['name' => '1Digital', 'expectedMessage' => 'The name must start with an uppercase character'],
        ];
    }

    private function removeTheme(string $pluginName): bool
    {
        $directory = $this->projectDir . '/custom/plugins/' . $pluginName;

        if (!is_dir($directory)) {
            return false;
        }

        (new Filesystem())->remove($directory);

        return true;
    }

    private function getCommandTester(): CommandTester
    {
        $themeCreateCommand = new ThemeCreateCommand(
            $this->projectDir
        );

        $commandTester = new CommandTester($themeCreateCommand);
        $application = new Application();
        $application->add($themeCreateCommand);

        return $commandTester;
    }
}
