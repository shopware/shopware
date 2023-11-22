<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Command\CreateAppCommand;
use Shopware\Core\Framework\App\Lifecycle\RefreshableAppDryRun;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(CreateAppCommand::class)]
class CreateAppCommandTest extends TestCase
{
    private const APP_NAME = 'TestApp';

    private RefreshableAppDryRun $appLifecycle;

    private string $appDir;

    protected function setUp(): void
    {
        $this->appLifecycle = new RefreshableAppDryRun();
        $this->appDir = __DIR__ . '/_fixtures/create-app-project';
    }

    protected function tearDown(): void
    {
        $this->removeApp();
    }

    public function testSuccessfulCreateCommand(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute(['name' => self::APP_NAME]);

        static::assertStringContainsString(
            'Creating app structure under TestApp',
            (string) preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true)))
        );

        static::assertFileExists($this->appDir . '/TestApp/manifest.xml');
        static::assertEquals(
            <<<EOL
            <?xml version="1.0" encoding="UTF-8"?>
            <manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/shopware/trunk/src/Core/Framework/App/Manifest/Schema/manifest-2.0.xsd">
                <meta>
                    <name>TestApp</name>
                    <label>My Example App</label>
                    <description>A description</description>
                    <author>Your Company Ltd.</author>
                    <copyright>(c) by Your Company Ltd.</copyright>
                    <version>1.0.0</version>
                    <icon></icon>
                    <license>MIT</license>
                </meta>
            </manifest>
            EOL,
            file_get_contents($this->appDir . '/TestApp/manifest.xml')
        );
    }

    public function testSuccessfulCreateCommandWithTheme(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute(['name' => self::APP_NAME, '--theme' => true]);

        static::assertStringContainsString(
            'Creating app structure under TestApp',
            (string) preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true)))
        );

        static::assertFileExists($this->appDir . '/TestApp/manifest.xml');
        static::assertFileExists($this->appDir . '/TestApp/Resources/theme.json');
        static::assertEquals(
            <<<EOL
            <?xml version="1.0" encoding="UTF-8"?>
            <manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/shopware/trunk/src/Core/Framework/App/Manifest/Schema/manifest-2.0.xsd">
                <meta>
                    <name>TestApp</name>
                    <label>My Example App</label>
                    <description>A description</description>
                    <author>Your Company Ltd.</author>
                    <copyright>(c) by Your Company Ltd.</copyright>
                    <version>1.0.0</version>
                    <icon></icon>
                    <license>MIT</license>
                </meta>
            </manifest>
            EOL,
            file_get_contents($this->appDir . '/TestApp/manifest.xml')
        );

        static::assertEquals(
            <<<EOL
            {
              "name": "TestApp",
              "author": "Your Company Ltd.",
              "views": [
                 "@Storefront",
                 "@Plugins",
                 "@TestApp"
              ],
              "style": [
                "app/storefront/src/scss/overrides.scss",
                "@Storefront",
                "app/storefront/src/scss/base.scss"
              ],
              "script": [
                "@Storefront",
                "app/storefront/dist/storefront/js/test_app.js"
              ],
              "asset": [
                "@Storefront",
                "app/storefront/src/assets"
              ]
            }
            EOL,
            file_get_contents($this->appDir . '/TestApp/Resources/theme.json')
        );
    }

    public function testAppIsInstalledIfRequestedInteractively(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->setInputs(['', '', '', '', '', '', '', 'y']);
        $commandTester->execute(['name' => self::APP_NAME]);

        static::assertStringContainsString(
            'Creating app structure under TestApp',
            (string) preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true)))
        );

        static::assertFileExists($this->appDir . '/TestApp/manifest.xml');

        static::assertEquals(
            <<<EOL
            <?xml version="1.0" encoding="UTF-8"?>
            <manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/shopware/trunk/src/Core/Framework/App/Manifest/Schema/manifest-2.0.xsd">
                <meta>
                    <name>TestApp</name>
                    <label>My Example App</label>
                    <description>A description</description>
                    <author>Your Company Ltd.</author>
                    <copyright>(c) by Your Company Ltd.</copyright>
                    <version>1.0.0</version>
                    <icon></icon>
                    <license>MIT</license>
                </meta>
            </manifest>
            EOL,
            file_get_contents($this->appDir . '/TestApp/manifest.xml')
        );

        static::assertStringContainsString(
            'App TestApp has been successfully installed.',
            (string) preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true)))
        );

        static::assertCount(1, $this->appLifecycle->getToBeInstalled());
        static::assertArrayHasKey(self::APP_NAME, $this->appLifecycle->getToBeInstalled());
    }

    public function testAppIsInstalledIfRequestedViaOption(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->execute(['name' => self::APP_NAME, '--install' => true]);

        static::assertStringContainsString(
            'Creating app structure under TestApp',
            (string) preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true)))
        );

        static::assertFileExists($this->appDir . '/TestApp/manifest.xml');

        static::assertEquals(
            <<<EOL
            <?xml version="1.0" encoding="UTF-8"?>
            <manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/shopware/trunk/src/Core/Framework/App/Manifest/Schema/manifest-2.0.xsd">
                <meta>
                    <name>TestApp</name>
                    <label>My Example App</label>
                    <description>A description</description>
                    <author>Your Company Ltd.</author>
                    <copyright>(c) by Your Company Ltd.</copyright>
                    <version>1.0.0</version>
                    <icon></icon>
                    <license>MIT</license>
                </meta>
            </manifest>
            EOL,
            file_get_contents($this->appDir . '/TestApp/manifest.xml')
        );

        static::assertStringContainsString(
            'App TestApp has been successfully installed.',
            (string) preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true)))
        );

        static::assertCount(1, $this->appLifecycle->getToBeInstalled());
        static::assertArrayHasKey(self::APP_NAME, $this->appLifecycle->getToBeInstalled());
    }

    public function testCommandFailsOnDuplicate(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute(['name' => self::APP_NAME]);

        static::assertStringContainsString(
            'Creating app structure under TestApp',
            (string) preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true)))
        );

        $commandTester->execute(['name' => self::APP_NAME]);

        static::assertStringContainsString(
            'Creating app structure under TestApp',
            (string) preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true)))
        );

        static::assertStringContainsString(
            'App directory TestApp already exists',
            (string) preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true)))
        );
    }

    /**
     * @param array{
     *     name: string,
     *     label?: string,
     *     description?: string,
     *     version?: string
     * } $input
     */
    #[DataProvider('invalidInputProvider')]
    public function testCommandFailsWithInvalidInput(array $input, string $expectedMessage): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute($input);

        static::assertFileDoesNotExist($this->appDir . '/TestApp/manifest.xml');

        static::assertStringContainsString(
            $expectedMessage,
            (string) preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true)))
        );
    }

    public static function invalidInputProvider(): \Generator
    {
        yield 'invalid_name' => [
            [
                'name' => 'smo',
            ],
            'The app name is too short (min 4 characters), contains invalid characters',
        ];

        yield [
            [
                'name' => '%app%',
            ],
            'The app name is too short (min 4 characters), contains invalid characters',
        ];

        yield [
            [
                'name' => 'app-',
            ],
            'The app name is too short (min 4 characters), contains invalid characters',
        ];

        yield [
            [
                'name' => '1app',
            ],
            'The app name is too short (min 4 characters), contains invalid characters',
        ];

        yield [
            [
                'name' => 'my_app',
                'label' => '%nospecialchars$',
            ],
            'The app label contains invalid characters. Only alphanumerics and whitespaces are allowed.',
        ];

        yield [
            [
                'name' => 'my_app',
                'label' => 'My Awesome App',
                'description' => '%nospecialchars$',
            ],
            'The app description contains invalid characters. Only alphanumerics and whitespaces are allowed.',
        ];

        yield [
            [
                'name' => 'my_app',
                'label' => 'My Awesome App',
                'description' => 'My Awesome App',
                'version' => 'version1',
            ],
            'App version must be a valid Semver string.',
        ];

        yield [
            [
                'name' => 'my_app',
                'label' => 'My Awesome App',
                'description' => 'My Awesome App',
                'version' => '1.2',
            ],
            'App version must be a valid Semver string.',
        ];
    }

    private function removeApp(): void
    {
        $directory = $this->appDir . '/' . self::APP_NAME;

        if (!is_dir($directory)) {
            return;
        }

        (new Filesystem())->remove($directory);
    }

    private function getCommandTester(): CommandTester
    {
        $appCreateCommand = new CreateAppCommand($this->appLifecycle, $this->appDir);

        $commandTester = new CommandTester($appCreateCommand);
        $application = new Application();
        $application->add($appCreateCommand);

        return $commandTester;
    }
}
