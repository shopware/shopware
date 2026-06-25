<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Theme\Command\ThemeCreateCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Exception\IOException;
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
        $this->removeTheme();
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

    public function testCommandFailsWhenDirectoryCannotBeCreated(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('mkdir')->willThrowException(new IOException('Permission denied'));

        $commandTester = $this->getCommandTester($filesystem);
        $commandTester->execute(['theme-name' => self::THEME_NAME]);

        static::assertStringContainsString('Unable to create directory', $commandTester->getDisplay(true));
        static::assertSame(Command::FAILURE, $commandTester->getStatusCode());
    }

    public function testCommandFailsOnDuplicate(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute(['theme-name' => self::THEME_NAME]);

        static::assertStringContainsString('Creating theme structure under', $commandTester->getDisplay(true));

        $commandTester->execute(['theme-name' => self::THEME_NAME]);

        $result = preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true)));
        static::assertIsString($result);
        static::assertStringContainsString('already exists', $result);
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
     * @return iterable<string, array<string, string>>
     */
    public static function commandFailsWithWrongNameDataProvider(): iterable
    {
        yield 'lowercase theme name fails validation' => ['name' => 'abc', 'expectedMessage' => 'The name must start with an uppercase character'];
        yield 'short theme name fails with length and character message' => ['name' => 'Abc', 'expectedMessage' => 'Theme name is too short (min 4 characters), contains invalid characters'];
        yield 'theme name starting with a digit fails validation' => ['name' => '1Digital', 'expectedMessage' => 'The name must start with an uppercase character'];
    }

    private function removeTheme(): bool
    {
        $directory = $this->projectDir . '/custom/';

        if (!is_dir($directory)) {
            return false;
        }

        (new Filesystem())->remove($directory);

        return true;
    }

    private function getCommandTester(?Filesystem $filesystem = null): CommandTester
    {
        $themeCreateCommand = new ThemeCreateCommand(
            $this->projectDir,
            $filesystem ?? new Filesystem(),
        );

        $commandTester = new CommandTester($themeCreateCommand);
        $application = new Application();
        $application->addCommand($themeCreateCommand);

        return $commandTester;
    }
}
