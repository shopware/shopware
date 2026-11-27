<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Command\UpgradeExtensionsCommand;
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
#[CoversClass(UpgradeExtensionsCommand::class)]
class UpgradeExtensionsCommandTest extends TestCase
{
    public function testUpgradeCommandRunsTheUpgradeEntryScript(): void
    {
        $administrationRoot = ExtensionToolingFixture::createAdministrationRoot(withToolingStub: true);

        $tester = new CommandTester(new UpgradeExtensionsCommand($this->kernel(), $administrationRoot));
        $tester->execute(['tooling-args' => ['--only=MyPlugin', '--write']]);

        $capture = ExtensionToolingFixture::readToolingCapture($administrationRoot);
        static::assertStringEndsWith('scripts/extensionTooling/upgrade.ts', $capture['argv'][1]);
        static::assertContains('--only=MyPlugin', $capture['argv']);
        static::assertContains('--write', $capture['argv']);

        ExtensionToolingFixture::removeAdministrationRoot($administrationRoot);
    }

    private function kernel(): KernelInterface
    {
        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn('/shop');

        return $kernel;
    }
}
