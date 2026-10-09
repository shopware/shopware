<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Command\AbstractExtensionToolingCommand;
use Shopware\Administration\Command\CheckExtensionsCommand;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 *
 * The bridge behaviour every tooling command inherits: the missing-dependency
 * guard, argument forwarding, exit-code propagation and app-root resolution.
 * `CheckExtensionsCommand` is only the concrete vehicle — driving a real named
 * subclass is what makes `execute()`/`configure()` coverage attributable.
 */
#[Package('framework')]
#[CoversClass(AbstractExtensionToolingCommand::class)]
class AbstractExtensionToolingCommandTest extends TestCase
{
    use ExtensionToolingCommandTestBehaviour;

    public function testFailsWithNpmCiGuidanceWhenNodeDependenciesAreMissing(): void
    {
        // A vendor/flex install ships the tooling code but not its node_modules.
        $administrationRoot = $this->createAdministrationRoot(withToolingStub: false);

        $tester = new CommandTester(new CheckExtensionsCommand($this->kernel(), $administrationRoot));
        $exitCode = $tester->execute([]);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('npm ci', $tester->getDisplay(true));
        static::assertFileDoesNotExist(
            $administrationRoot . '/.tooling-capture.json',
            'the tooling must not be spawned when its dependencies are missing',
        );

        $this->removeAdministrationRoot($administrationRoot);
    }

    public function testForwardsArgumentsAndProjectRootAndPropagatesExitCode(): void
    {
        // A non-zero tooling exit (findings) must reach the shell unchanged.
        $administrationRoot = $this->createAdministrationRoot(withToolingStub: true, stubExitCode: 1);

        $tester = new CommandTester(new CheckExtensionsCommand($this->kernel(), $administrationRoot));
        $exitCode = $tester->execute(['tooling-args' => ['--only=MyPlugin', '--all']]);

        static::assertSame(1, $exitCode);

        $capture = $this->readToolingCapture($administrationRoot);
        static::assertSame('/shop', $capture['project_root']);
        static::assertSame(realpath($administrationRoot), $capture['cwd']);
        static::assertContains('--transpileOnly', $capture['argv']);
        static::assertContains('--only=MyPlugin', $capture['argv']);
        static::assertContains('--all', $capture['argv']);

        $this->removeAdministrationRoot($administrationRoot);
    }

    public function testAdministrationRootResolvesToTheBundleResourcesPathByDefault(): void
    {
        $command = new class($this->kernel()) extends CheckExtensionsCommand {
            public function exposedAdministrationRoot(): string
            {
                return $this->administrationRoot();
            }
        };

        static::assertStringEndsWith('/Resources/app/administration', $command->exposedAdministrationRoot());
    }
}
