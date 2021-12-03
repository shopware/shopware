<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Storefront\Theme\Command\ThemeCreateCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ThemeCreateCommandTest extends TestCase
{
    use KernelTestBehaviour;

    private const ThemeName = 'TestPlugin';

    private string $projectDir;

    public function setUp(): void
    {
        $this->projectDir = $this->getContainer()->getParameter('kernel.project_dir');
    }

    public function tearDown(): void
    {
        $this->removeTheme(self::ThemeName);
    }

    public function testSuccessfulCreateCommand(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute(['theme-name' => self::ThemeName]);

        static::assertStringContainsString('Creating theme structure under', preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true))));
    }

    public function testCommandFailsOnDuplicate(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute(['theme-name' => self::ThemeName]);

        static::assertStringContainsString('Creating theme structure under', $commandTester->getDisplay(true));

        $commandTester->execute(['theme-name' => self::ThemeName]);

        static::assertStringContainsString(self::ThemeName . ' already exists', preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true))));
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

    public function commandFailsWithWrongNameDataProvider()
    {
        return [
            ['name' => 'abc', 'expectedMessage' => 'The name must start with an uppercase character'],
            ['name' => 'Abc', 'expectedMessage' => 'Theme name is too short (min 4 characters), contains invalid characters'],
            ['name' => '1Digital', 'expectedMessage' => 'The name must start with an uppercase character'],
        ];
    }

    private function removeTheme($pluginName): bool
    {
        $directory = $this->projectDir . '/custom/plugins/' . $pluginName;

        if (!is_dir($directory)) {
            return false;
        }

        $this->deleteDirectory($directory);

        return true;
    }

    private function deleteDirectory($path): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getRealPath());
            } else {
                unlink($fileInfo->getRealPath());
            }
        }

        rmdir($path);
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
