<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\Command\ThemeCreateCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('discovery')]
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
        $pluginDirectory = $this->projectDir . 'custom/plugins/' . self::THEME_NAME;
        $expectedDirectory = $pluginDirectory . '/src/';

        $commandTester = $this->getCommandTester();

        $commandTester->execute(['theme-name' => self::THEME_NAME]);
        $result = preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true)));

        static::assertIsString($result);
        static::assertStringContainsString('Creating theme structure under', $result);
        static::assertDirectoryExists($expectedDirectory);
        static::assertFileExists($expectedDirectory . 'TestPlugin.php');
        static::assertDirectoryExists($expectedDirectory . 'Resources');
        static::assertFileExists($expectedDirectory . 'Resources/theme.json');

        static::assertDirectoryDoesNotExist($expectedDirectory . 'Resources/config');
        static::assertDirectoryDoesNotExist($expectedDirectory . 'Resources/snippet');
        static::assertDirectoryDoesNotExist($expectedDirectory . 'Resources/app/storefront/src/scss/abstracts');

        $composerJson = file_get_contents($pluginDirectory . '/composer.json');
        static::assertIsString($composerJson);
        $composer = json_decode($composerJson, true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame('custom/test-plugin', $composer['name']);
        static::assertSame('~6.7.0', $composer['require']['shopware/core']);
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

    public function testSuccessfulCreateFullCommand(): void
    {
        $expectedDirectory = $this->projectDir . 'custom/plugins/' . self::THEME_NAME . '/src/';

        $commandTester = $this->getCommandTester();

        $commandTester->execute(['theme-name' => self::THEME_NAME, '--full' => true]);
        $result = preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true)));

        static::assertIsString($result);
        static::assertStringContainsString('Creating theme structure under', $result);

        static::assertFileExists($expectedDirectory . 'Resources/config/config.xml');
        static::assertFileExists($expectedDirectory . 'Resources/snippet/storefront.de-DE.json');
        static::assertFileExists($expectedDirectory . 'Resources/snippet/storefront.en-GB.json');

        $scssBase = $expectedDirectory . 'Resources/app/storefront/src/scss/';
        static::assertFileExists($scssBase . 'base.scss');
        static::assertFileExists($scssBase . 'abstracts/_variables.scss');
        static::assertFileExists($scssBase . 'abstracts/_mixins.scss');
        static::assertFileExists($scssBase . 'base/_reset.scss');
        static::assertFileExists($scssBase . 'base/_typography.scss');
        static::assertFileExists($scssBase . 'components/_buttons.scss');
        static::assertFileExists($scssBase . 'layout/_header.scss');
        static::assertFileExists($scssBase . 'layout/_footer.scss');
        static::assertFileExists($scssBase . 'layout/_navigation.scss');
        static::assertFileExists($scssBase . 'pages/_home.scss');

        $baseScssContent = file_get_contents($scssBase . 'base.scss');
        static::assertIsString($baseScssContent);
        static::assertStringContainsString('@import "abstracts/variables";', $baseScssContent);
        static::assertStringContainsString('@import "pages/home";', $baseScssContent);
    }

    public function testSuccessfulCreateWithConfigOnly(): void
    {
        $expectedDirectory = $this->projectDir . 'custom/plugins/' . self::THEME_NAME . '/src/';

        $commandTester = $this->getCommandTester();
        $commandTester->execute(['theme-name' => self::THEME_NAME, '--with-config' => true]);

        static::assertFileExists($expectedDirectory . 'Resources/config/config.xml');
        static::assertDirectoryDoesNotExist($expectedDirectory . 'Resources/snippet');
        static::assertDirectoryDoesNotExist($expectedDirectory . 'Resources/app/storefront/src/scss/abstracts');
        static::assertFileExists($expectedDirectory . 'Resources/app/storefront/src/scss/base.scss');
    }

    public function testSuccessfulCreateWithSnippetsOnly(): void
    {
        $expectedDirectory = $this->projectDir . 'custom/plugins/' . self::THEME_NAME . '/src/';

        $commandTester = $this->getCommandTester();
        $commandTester->execute(['theme-name' => self::THEME_NAME, '--with-snippets' => true]);

        static::assertFileExists($expectedDirectory . 'Resources/snippet/storefront.de-DE.json');
        static::assertFileExists($expectedDirectory . 'Resources/snippet/storefront.en-GB.json');
        static::assertDirectoryDoesNotExist($expectedDirectory . 'Resources/config');
        static::assertDirectoryDoesNotExist($expectedDirectory . 'Resources/app/storefront/src/scss/abstracts');
    }

    public function testSuccessfulCreateWithScssOnly(): void
    {
        $expectedDirectory = $this->projectDir . 'custom/plugins/' . self::THEME_NAME . '/src/';

        $commandTester = $this->getCommandTester();
        $commandTester->execute(['theme-name' => self::THEME_NAME, '--with-scss' => true]);

        $scssBase = $expectedDirectory . 'Resources/app/storefront/src/scss/';
        static::assertFileExists($scssBase . 'base.scss');
        static::assertFileExists($scssBase . 'abstracts/_variables.scss');
        static::assertFileExists($scssBase . 'pages/_home.scss');
        static::assertDirectoryDoesNotExist($expectedDirectory . 'Resources/config');
        static::assertDirectoryDoesNotExist($expectedDirectory . 'Resources/snippet');
    }

    public function testCommandFailsWhenDirectoryCannotBeCreated(): void
    {
        $filesystem = static::createStub(Filesystem::class);
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
