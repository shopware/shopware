<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Command\TestExtensionsCommand;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 *
 * The bridge behaviour this command inherits is covered by
 * AbstractExtensionToolingCommandTest; the entry script is all this subclass owns.
 */
#[Package('framework')]
#[CoversClass(TestExtensionsCommand::class)]
class TestExtensionsCommandTest extends TestCase
{
    public function testTestCommandRunsTheTestEntryScriptWithTheJestOptions(): void
    {
        $administrationRoot = ExtensionToolingFixture::createAdministrationRoot(withToolingStub: true);

        $tester = new CommandTester(new TestExtensionsCommand($this->kernel(), $administrationRoot));
        $tester->execute(['tooling-args' => ['--only=MyPlugin', '--', '--watch']]);

        $capture = ExtensionToolingFixture::readToolingCapture($administrationRoot);
        static::assertStringEndsWith('scripts/extensionTooling/test.ts', $capture['argv'][1]);
        static::assertSame(['--only=MyPlugin', '--', '--watch'], \array_slice($capture['argv'], 2));

        ExtensionToolingFixture::removeAdministrationRoot($administrationRoot);
    }

    private function kernel(): KernelInterface
    {
        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn('/shop');

        return $kernel;
    }
}
