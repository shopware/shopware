<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Storefront\Theme\Command\ThemePrepareIconsCommand;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ThemePrepareIconsCommandTest extends TestCase
{
    use KernelTestBehaviour;

    public string $testDir;

    private ThemeService $themeService;

    private EntityRepositoryInterface $salesChannelRepository;

    private MockObject $pluginRegistry;

    private EntityRepositoryInterface $themeRepository;

    private string $projectDir;

    public function setUp(): void
    {
        $this->testDir = str_replace('/platform', '', $this->getContainer()->getParameter('kernel.project_dir'))
            . '/platform/src/Storefront/Test/Theme/fixtures/ThemePrepareIconsCommandIconsPath/';
        static::assertDirectoryExists($this->testDir, 'Testdir: ' . $this->testDir . ' not found!');
        @array_map('unlink', glob($this->testDir . 'processed/*'));
        @rmdir($this->testDir . 'processed');
    }

    public function tearDown(): void
    {
        static::assertDirectoryExists($this->testDir, 'Testdir: ' . $this->testDir . ' not found!');
        @array_map('unlink', glob($this->testDir . 'processed/*'));
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

        static::assertStringContainsString('Start Icon preparation', $commandTester->getDisplay());

        static::assertStringContainsString('[WARNING] simplexml_load_string(): Entity: line 1: parser error', $commandTester->getDisplay());

        static::assertStringContainsString('mandIconsPath/invalid.svg', $commandTester->getDisplay());

        static::assertStringContainsString('[OK] Processed 1 icons', $commandTester->getDisplay());

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
}
