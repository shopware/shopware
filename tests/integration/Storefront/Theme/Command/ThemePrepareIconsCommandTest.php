<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\Command\ThemePrepareIconsCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The command writes the processed icons next to the sources, so it runs against a disposable copy
 * of the icon fixtures here; the argument validation is covered by the unit test.
 *
 * @internal
 */
#[Package('discovery')]
class ThemePrepareIconsCommandTest extends TestCase
{
    private string $iconsDir;

    private Filesystem $filesystem;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->iconsDir = sys_get_temp_dir() . '/' . uniqid('theme-prepare-icons-', true) . '/';
        $this->filesystem->mirror(__DIR__ . '/_fixtures/icons', $this->iconsDir);

        $command = new ThemePrepareIconsCommand();
        $this->commandTester = new CommandTester($command);
        $application = new Application();
        $application->addCommand($command);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->iconsDir);
    }

    public function testThemePrepareIconsCommand(): void
    {
        $this->commandTester->execute([
            'path' => $this->iconsDir,
            'package' => 'default',
        ]);

        static::assertStringContainsString('StartIconpreparation', $this->minimizedOutput($this->commandTester->getDisplay()));
        static::assertStringContainsString('[WARNING]Couldnotread', $this->minimizedOutput($this->commandTester->getDisplay()));
        static::assertStringContainsString('invalid.svg', $this->minimizedOutput($this->commandTester->getDisplay()));
        static::assertStringContainsString('Processed1icons', $this->minimizedOutput($this->commandTester->getDisplay()));

        $expectedIcon = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24" height="24" viewBox="0 0 24 24">'
            . '<defs>'
            . '<path d="M4 5c-.5523 0-1-.4477-1-1s.4477-1 1-1h16c.5523 0 1 .4477 1 1s-.4477 1-1 1H4zm3 4c-.5523 0-1-.4477-1-1s.4477-1 1-1h11c.5523 0 1 .4477 1 1s-.4477 1-1 1H7zm-1 4c-.5523 0-1-.4477-1-1s.4477-1 1-1h13c.5523 0 1 .4477 1 1s-.4477 1-1 1H6zm-2 4c-.5523 0-1-.4477-1-1s.4477-1 1-1h16c.5523 0 1 .4477 1 1s-.4477 1-1 1H4zm3 4c-.5523 0-1-.4477-1-1s.4477-1 1-1h11c.5523 0 1 .4477 1 1s-.4477 1-1 1H7z" id="icons-default-align-center" style="fill: #758CA3; fill-rule: evenodd" />'
            . '</defs>'
            . '<use xlink:href="#icons-default-align-center" />'
            . '</svg>';

        static::assertSame($expectedIcon, $this->filesystem->readFile($this->iconsDir . 'processed/valid.svg'));
        static::assertFileDoesNotExist($this->iconsDir . 'processed/invalid.svg');
        static::assertFileDoesNotExist($this->iconsDir . 'processed/null.svg');
    }

    public function testThemePrepareIconsCommandClean(): void
    {
        $this->commandTester->execute(
            [
                'path' => $this->iconsDir,
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

        static::assertSame($expectedIcon, $this->filesystem->readFile($this->iconsDir . 'processed/valid.svg'));
        static::assertFileDoesNotExist($this->iconsDir . 'processed/invalid.svg');
        static::assertFileDoesNotExist($this->iconsDir . 'processed/null.svg');
    }

    public function testThemePrepareIconsCommandFill(): void
    {
        $this->commandTester->execute(
            [
                'path' => $this->iconsDir,
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

        static::assertFileExists($this->iconsDir . 'processed/valid.svg');
        static::assertSame($expectedIcon, $this->filesystem->readFile($this->iconsDir . 'processed/valid.svg'));
        static::assertFileDoesNotExist($this->iconsDir . 'processed/invalid.svg');
        static::assertFileDoesNotExist($this->iconsDir . 'processed/null.svg');
    }

    private function minimizedOutput(string $output): string
    {
        return str_replace(' ', '', str_replace("\n", '', $output));
    }
}
