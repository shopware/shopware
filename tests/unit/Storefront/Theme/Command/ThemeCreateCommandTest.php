<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Theme\Command\ThemeCreateCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(ThemeCreateCommand::class)]
class ThemeCreateCommandTest extends TestCase
{
    private const THEME_NAME = 'TestPlugin';

    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = __DIR__ . '/../fixtures/ThemeCreateCommand/';
    }

    protected function tearDown(): void
    {
        $this->removeTheme(self::THEME_NAME);
    }

    public function testSuccessfulCreateCommand(): void
    {
        $expectedDirectory = $this->projectDir . 'custom/plugins/' . self::THEME_NAME . '/src/';

        $commandTester = $this->getCommandTester();

        $commandTester->execute(['theme-name' => self::THEME_NAME]);
        $result = preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true)));

        static::assertIsString($result);
        static::assertStringContainsString('Creating theme structure under', $result);
        static::assertDirectoryExists($expectedDirectory);
        static::assertFileExists($expectedDirectory . 'TestPlugin.php');
        static::assertDirectoryExists($expectedDirectory . 'Resources');
        static::assertFileExists($expectedDirectory . 'Resources/theme.json');
    }

    public function testSuccessfulCreateAsStaticCommand(): void
    {
        $expectedDirectory = $this->projectDir . 'custom/static-plugins/' . self::THEME_NAME . '/src/';

        $commandTester = $this->getCommandTester();

        $commandTester->execute(['theme-name' => self::THEME_NAME, '--static' => true]);
        $result = preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true)));

        static::assertIsString($result);
        static::assertStringContainsString('Creating theme structure under', $result);
        static::assertDirectoryExists($expectedDirectory);
        static::assertFileExists($expectedDirectory . 'TestPlugin.php');
        static::assertDirectoryExists($expectedDirectory . 'Resources');
        static::assertFileExists($expectedDirectory . 'Resources/theme.json');
    }

    public function testCommandFailsOnDuplicate(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute(['theme-name' => self::THEME_NAME]);

        $result = $commandTester->getDisplay(true);

        static::assertStringContainsString('Creating theme structure under', $result);

        $commandTester->execute(['theme-name' => self::THEME_NAME]);

        $result = preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true)));

        static::assertIsString($result);
        static::assertStringContainsString(self::THEME_NAME . ' already exists', $result);
    }

    #[DataProvider('commandFailsWithWrongNameDataProvider')]
    public function testCommandFailsWithWrongName(string $name, string $expectedMessage): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute(['theme-name' => $name]);
        $result = preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true)));
        static::assertIsString($result);
        static::assertStringContainsString($expectedMessage, $result);
    }

    /**
     * @return array<int, array<string, string>>
     */
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
        $directory = $this->projectDir . '/custom/';

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
