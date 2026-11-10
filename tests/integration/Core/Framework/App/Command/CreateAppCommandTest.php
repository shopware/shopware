<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Command\CreateAppCommand;
use Shopware\Core\Framework\App\Lifecycle\RefreshableAppDryRun;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class CreateAppCommandTest extends TestCase
{
    private const APP_NAME = 'TestApp';

    private RefreshableAppDryRun $appLifecycle;

    private Filesystem $filesystem;

    private string $appDir;

    protected function setUp(): void
    {
        $this->appLifecycle = new RefreshableAppDryRun();
        $this->filesystem = new Filesystem();
        $this->appDir = sys_get_temp_dir() . '/' . uniqid('create-app-', true);
        $this->filesystem->mkdir($this->appDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->appDir);
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
        static::assertSame(
            <<<EOL
            <?xml version="1.0" encoding="UTF-8"?>
            <manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                      xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/shopware/trunk/src/Core/Framework/App/Manifest/Schema/manifest-3.0.xsd">
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
        static::assertSame(
            <<<EOL
            <?xml version="1.0" encoding="UTF-8"?>
            <manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                      xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/shopware/trunk/src/Core/Framework/App/Manifest/Schema/manifest-3.0.xsd">
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

        static::assertSame(
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

        static::assertSame(
            <<<EOL
            <?xml version="1.0" encoding="UTF-8"?>
            <manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                      xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/shopware/trunk/src/Core/Framework/App/Manifest/Schema/manifest-3.0.xsd">
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

        static::assertSame(
            <<<EOL
            <?xml version="1.0" encoding="UTF-8"?>
            <manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                      xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/shopware/trunk/src/Core/Framework/App/Manifest/Schema/manifest-3.0.xsd">
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
            'Directory for app "TestApp" already exists',
            (string) preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true)))
        );
    }

    private function getCommandTester(): CommandTester
    {
        $appCreateCommand = new CreateAppCommand($this->appLifecycle, $this->appDir);

        $commandTester = new CommandTester($appCreateCommand);
        $application = new Application();
        $application->addCommand($appCreateCommand);

        return $commandTester;
    }
}
