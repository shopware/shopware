<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Administration\Command\CheckExtensionsCommand;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Spawns the tooling for real: the working directory, the environment and the exit code
 * have to survive the process boundary. The bridge logic itself is covered by the unit test
 * through the `runTooling()` seam.
 *
 * @internal
 */
#[Package('framework')]
class AbstractExtensionToolingCommandTest extends TestCase
{
    private string $administrationRoot;

    protected function setUp(): void
    {
        $this->administrationRoot = ExtensionToolingFixture::createAdministrationRoot(withToolingStub: true, stubExitCode: 1);
    }

    protected function tearDown(): void
    {
        ExtensionToolingFixture::removeAdministrationRoot($this->administrationRoot);
    }

    public function testForwardsArgumentsAndProjectRootAndPropagatesExitCode(): void
    {
        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn('/shop');

        $tester = new CommandTester(new CheckExtensionsCommand($kernel, $this->administrationRoot));
        $exitCode = $tester->execute(['tooling-args' => ['--only=MyPlugin', '--all']]);

        // A non-zero tooling exit (findings) must reach the shell unchanged.
        static::assertSame(1, $exitCode);

        $capture = ExtensionToolingFixture::readToolingCapture($this->administrationRoot);
        static::assertSame('/shop', $capture['project_root']);
        static::assertSame(realpath($this->administrationRoot), $capture['cwd']);
        static::assertContains('--transpileOnly', $capture['argv']);
        static::assertStringEndsWith('scripts/extensionTooling/check.ts', $capture['argv'][1]);
        static::assertContains('--only=MyPlugin', $capture['argv']);
        static::assertContains('--all', $capture['argv']);
    }
}
