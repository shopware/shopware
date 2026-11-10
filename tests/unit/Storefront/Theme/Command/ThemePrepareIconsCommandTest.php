<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\Command\ThemePrepareIconsCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemePrepareIconsCommand::class)]
class ThemePrepareIconsCommandTest extends TestCase
{
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $command = new ThemePrepareIconsCommand();
        $this->commandTester = new CommandTester($command);
        $application = new Application();
        $application->addCommand($command);
    }

    public function testThemePrepareIconsCommandMissingPackageArg(): void
    {
        $this->expectExceptionObject(new RuntimeException('Not enough arguments (missing: "package")'));
        $this->commandTester->execute([
            'path' => '/icons',
        ]);
    }

    public function testThemePrepareIconsCommandMissingPathArg(): void
    {
        $this->expectExceptionObject(new RuntimeException('Not enough arguments (missing: "path")'));
        $this->commandTester->execute([
            'package' => 'default',
        ]);
    }
}
