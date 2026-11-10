<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\Framework\Util\InMemoryUtilFilesystem;
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

    private InMemoryUtilFilesystem $filesystem;

    protected function setUp(): void
    {
        $this->projectDir = '/project/';
        $this->filesystem = new InMemoryUtilFilesystem();
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
        static::assertTrue($this->filesystem->exists($expectedDirectory));
        static::assertTrue($this->filesystem->exists($expectedDirectory . 'TestPlugin.php'));
        static::assertTrue($this->filesystem->exists($expectedDirectory . 'Resources'));
        static::assertTrue($this->filesystem->exists($expectedDirectory . 'Resources/theme.json'));

        static::assertFalse($this->filesystem->exists($expectedDirectory . 'Resources/config'));
        static::assertFalse($this->filesystem->exists($expectedDirectory . 'Resources/snippet'));
        static::assertFalse($this->filesystem->exists($expectedDirectory . 'Resources/app/storefront/src/scss/abstracts'));

        $composer = json_decode($this->filesystem->readFile($pluginDirectory . '/composer.json'), true, flags: \JSON_THROW_ON_ERROR);
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
        static::assertTrue($this->filesystem->exists($expectedDirectory));
        static::assertTrue($this->filesystem->exists($expectedDirectory . 'TestPlugin.php'));
        static::assertTrue($this->filesystem->exists($expectedDirectory . 'Resources'));
        static::assertTrue($this->filesystem->exists($expectedDirectory . 'Resources/theme.json'));
    }

    public function testSuccessfulCreateFullCommand(): void
    {
        $expectedDirectory = $this->projectDir . 'custom/plugins/' . self::THEME_NAME . '/src/';

        $commandTester = $this->getCommandTester();

        $commandTester->execute(['theme-name' => self::THEME_NAME, '--full' => true]);
        $result = preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true)));

        static::assertIsString($result);
        static::assertStringContainsString('Creating theme structure under', $result);

        static::assertTrue($this->filesystem->exists($expectedDirectory . 'Resources/config/config.xml'));
        static::assertTrue($this->filesystem->exists($expectedDirectory . 'Resources/snippet/storefront.de-DE.json'));
        static::assertTrue($this->filesystem->exists($expectedDirectory . 'Resources/snippet/storefront.en-GB.json'));

        $scssBase = $expectedDirectory . 'Resources/app/storefront/src/scss/';
        static::assertTrue($this->filesystem->exists($scssBase . 'base.scss'));
        static::assertTrue($this->filesystem->exists($scssBase . 'abstracts/_variables.scss'));
        static::assertTrue($this->filesystem->exists($scssBase . 'abstracts/_mixins.scss'));
        static::assertTrue($this->filesystem->exists($scssBase . 'base/_reset.scss'));
        static::assertTrue($this->filesystem->exists($scssBase . 'base/_typography.scss'));
        static::assertTrue($this->filesystem->exists($scssBase . 'components/_buttons.scss'));
        static::assertTrue($this->filesystem->exists($scssBase . 'layout/_header.scss'));
        static::assertTrue($this->filesystem->exists($scssBase . 'layout/_footer.scss'));
        static::assertTrue($this->filesystem->exists($scssBase . 'layout/_navigation.scss'));
        static::assertTrue($this->filesystem->exists($scssBase . 'pages/_home.scss'));

        $baseScssContent = $this->filesystem->readFile($scssBase . 'base.scss');
        static::assertStringContainsString('@import "abstracts/variables";', $baseScssContent);
        static::assertStringContainsString('@import "pages/home";', $baseScssContent);
    }

    public function testSuccessfulCreateWithConfigOnly(): void
    {
        $expectedDirectory = $this->projectDir . 'custom/plugins/' . self::THEME_NAME . '/src/';

        $commandTester = $this->getCommandTester();
        $commandTester->execute(['theme-name' => self::THEME_NAME, '--with-config' => true]);

        static::assertTrue($this->filesystem->exists($expectedDirectory . 'Resources/config/config.xml'));
        static::assertFalse($this->filesystem->exists($expectedDirectory . 'Resources/snippet'));
        static::assertFalse($this->filesystem->exists($expectedDirectory . 'Resources/app/storefront/src/scss/abstracts'));
        static::assertTrue($this->filesystem->exists($expectedDirectory . 'Resources/app/storefront/src/scss/base.scss'));
    }

    public function testSuccessfulCreateWithSnippetsOnly(): void
    {
        $expectedDirectory = $this->projectDir . 'custom/plugins/' . self::THEME_NAME . '/src/';

        $commandTester = $this->getCommandTester();
        $commandTester->execute(['theme-name' => self::THEME_NAME, '--with-snippets' => true]);

        static::assertTrue($this->filesystem->exists($expectedDirectory . 'Resources/snippet/storefront.de-DE.json'));
        static::assertTrue($this->filesystem->exists($expectedDirectory . 'Resources/snippet/storefront.en-GB.json'));
        static::assertFalse($this->filesystem->exists($expectedDirectory . 'Resources/config'));
        static::assertFalse($this->filesystem->exists($expectedDirectory . 'Resources/app/storefront/src/scss/abstracts'));
    }

    public function testSuccessfulCreateWithScssOnly(): void
    {
        $expectedDirectory = $this->projectDir . 'custom/plugins/' . self::THEME_NAME . '/src/';

        $commandTester = $this->getCommandTester();
        $commandTester->execute(['theme-name' => self::THEME_NAME, '--with-scss' => true]);

        $scssBase = $expectedDirectory . 'Resources/app/storefront/src/scss/';
        static::assertTrue($this->filesystem->exists($scssBase . 'base.scss'));
        static::assertTrue($this->filesystem->exists($scssBase . 'abstracts/_variables.scss'));
        static::assertTrue($this->filesystem->exists($scssBase . 'pages/_home.scss'));
        static::assertFalse($this->filesystem->exists($expectedDirectory . 'Resources/config'));
        static::assertFalse($this->filesystem->exists($expectedDirectory . 'Resources/snippet'));
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
        yield 'short theme name fails with length and character message' => ['name' => 'Abc', 'expectedMessage' => 'Theme name is too short (min 4 characters) or contains invalid characters'];
        yield 'theme name starting with a digit fails validation' => ['name' => '1Digital', 'expectedMessage' => 'The name must start with an uppercase character'];
        yield 'theme name with a trailing newline fails validation' => ['name' => "Abcd\n", 'expectedMessage' => 'Theme name is too short (min 4 characters) or contains invalid characters'];
    }

    private function getCommandTester(?Filesystem $filesystem = null): CommandTester
    {
        $themeCreateCommand = new ThemeCreateCommand(
            $this->projectDir,
            $filesystem ?? $this->filesystem,
        );

        $commandTester = new CommandTester($themeCreateCommand);
        $application = new Application();
        $application->addCommand($themeCreateCommand);

        return $commandTester;
    }
}
