<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Storefront\Theme\Command\ThemePrepareIconsCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class ThemePrepareIconsCommandTest extends TestCase
{
    use KernelTestBehaviour;

    public string $testDir;

    protected function setUp(): void
    {
        $this->testDir = $this->getContainer()->getParameter('storefrontRoot')
            . '/Test/Theme/fixtures/ThemePrepareIconsCommandIconsPath/';
        static::assertDirectoryExists($this->testDir, 'Testdir: ' . $this->testDir . ' not found!');
        $testFiles = glob($this->testDir . 'processed/*');
        static::assertIsArray($testFiles);
        @array_map('unlink', $testFiles);
        @rmdir($this->testDir . 'processed');
    }

    protected function tearDown(): void
    {
        static::assertDirectoryExists($this->testDir, 'Testdir: ' . $this->testDir . ' not found!');
        $testFiles = glob($this->testDir . 'processed/*');
        static::assertIsArray($testFiles);
        @array_map('unlink', $testFiles);
        @rmdir($this->testDir . 'processed');
    }

    public function testThemePrepareIconsCommandMissingPackageArg(): void
    {
        $command = new ThemePrepareIconsCommand();
        $commandTester = new CommandTester($command);
        $application = new Application();
        $application->add($command);

        static::expectExceptionMessage('Not enough arguments (missing: "package")');
        $commandTester->execute([
            'path' => $this->testDir,
        ]);
    }

    public function testThemePrepareIconsCommandMissingPathArg(): void
    {
        $command = new ThemePrepareIconsCommand();
        $commandTester = new CommandTester($command);
        $application = new Application();
        $application->add($command);

        static::expectExceptionMessage('Not enough arguments (missing: "path")');
        $commandTester->execute([
            'package' => 'default',
        ]);
    }

    public function testThemePrepareIconsCommand(): void
    {
        $command = new ThemePrepareIconsCommand();
        $commandTester = new CommandTester($command);
        $application = new Application();
        $application->add($command);

        $commandTester->execute([
            'path' => $this->testDir,
            'package' => 'default',
        ]);

        static::assertStringContainsString('StartIconpreparation', $this->minimizedOutput($commandTester->getDisplay()));

        static::assertStringContainsString('[WARNING]StringcouldnotbeparsedasXML', $this->minimizedOutput($commandTester->getDisplay()));

        static::assertStringContainsString('mandIconsPath/invalid.svg', $this->minimizedOutput($commandTester->getDisplay()));

        static::assertStringContainsString('Processed1icons', $this->minimizedOutput($commandTester->getDisplay()));

        $expectedIcon = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24" height="24" viewBox="0 0 24 24">'
. '<defs>'
. '<path d="M4 5c-.5523 0-1-.4477-1-1s.4477-1 1-1h16c.5523 0 1 .4477 1 1s-.4477 1-1 1H4zm3 4c-.5523 0-1-.4477-1-1s.4477-1 1-1h11c.5523 0 1 .4477 1 1s-.4477 1-1 1H7zm-1 4c-.5523 0-1-.4477-1-1s.4477-1 1-1h13c.5523 0 1 .4477 1 1s-.4477 1-1 1H6zm-2 4c-.5523 0-1-.4477-1-1s.4477-1 1-1h16c.5523 0 1 .4477 1 1s-.4477 1-1 1H4zm3 4c-.5523 0-1-.4477-1-1s.4477-1 1-1h11c.5523 0 1 .4477 1 1s-.4477 1-1 1H7z" id="icons-default-align-center" style="fill: #758CA3; fill-rule: evenodd" />'
. '</defs>'
. '<use xlink:href="#icons-default-align-center" />'
. '</svg>';

        static::assertSame($expectedIcon, file_get_contents($this->testDir . 'processed/valid.svg'));
        static::assertFileDoesNotExist($this->testDir . 'processed/invalid.svg');
    }

    public function testThemePrepareIconsCommandClean(): void
    {
        $command = new ThemePrepareIconsCommand();
        $commandTester = new CommandTester($command);
        $application = new Application();
        $application->add($command);

        $commandTester->execute(
            [
                'path' => $this->testDir,
                'package' => 'default',
                '--cleanup' => 'true',
            ]
        );

        $expectedIcon = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24" height="24" viewBox="0 0 24 24">'
            . '<defs>'
            . '<path d="M4 5c-.5523 0-1-.4477-1-1s.4477-1 1-1h16c.5523 0 1 .4477 1 1s-.4477 1-1 1H4zm3 4c-.5523 0-1-.4477-1-1s.4477-1 1-1h11c.5523 0 1 .4477 1 1s-.4477 1-1 1H7zm-1 4c-.5523 0-1-.4477-1-1s.4477-1 1-1h13c.5523 0 1 .4477 1 1s-.4477 1-1 1H6zm-2 4c-.5523 0-1-.4477-1-1s.4477-1 1-1h16c.5523 0 1 .4477 1 1s-.4477 1-1 1H4zm3 4c-.5523 0-1-.4477-1-1s.4477-1 1-1h11c.5523 0 1 .4477 1 1s-.4477 1-1 1H7z" id="icons-default-valid" />'
            . '</defs>'
            . '<use xlink:href="#icons-default-valid" />'
            . '</svg>';

        static::assertSame($expectedIcon, file_get_contents($this->testDir . 'processed/valid.svg'));
        static::assertFileDoesNotExist($this->testDir . 'processed/invalid.svg');
    }

    public function testThemePrepareIconsCommandFill(): void
    {
        $command = new ThemePrepareIconsCommand();
        $commandTester = new CommandTester($command);
        $application = new Application();
        $application->add($command);

        $commandTester->execute(
            [
                'path' => $this->testDir,
                'package' => 'default',
                '--cleanup' => 'true',
                '--fillcolor' => '#12EF12',
                '--fillrule' => 'nonzero',
            ]
        );

        $expectedIcon = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24" height="24" viewBox="0 0 24 24">'
            . '<defs>'
            . '<path d="M4 5c-.5523 0-1-.4477-1-1s.4477-1 1-1h16c.5523 0 1 .4477 1 1s-.4477 1-1 1H4zm3 4c-.5523 0-1-.4477-1-1s.4477-1 1-1h11c.5523 0 1 .4477 1 1s-.4477 1-1 1H7zm-1 4c-.5523 0-1-.4477-1-1s.4477-1 1-1h13c.5523 0 1 .4477 1 1s-.4477 1-1 1H6zm-2 4c-.5523 0-1-.4477-1-1s.4477-1 1-1h16c.5523 0 1 .4477 1 1s-.4477 1-1 1H4zm3 4c-.5523 0-1-.4477-1-1s.4477-1 1-1h11c.5523 0 1 .4477 1 1s-.4477 1-1 1H7z" id="icons-default-valid" />'
            . '</defs>'
            . '<use xlink:href="#icons-default-valid" fill="#12EF12" fill-rule="nonzero" />'
            . '</svg>';

        static::assertSame($expectedIcon, file_get_contents($this->testDir . 'processed/valid.svg'));
        static::assertFileDoesNotExist($this->testDir . 'processed/invalid.svg');
    }

    private function minimizedOutput(string $output): string
    {
        return str_replace(' ', '', str_replace("\n", '', $output));
    }
}
