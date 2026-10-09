<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Command\CheckExtensionsCommand;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 *
 * The bridge behaviour this command inherits is covered by
 * AbstractExtensionToolingCommandTest; the entry script is all this subclass owns.
 */
#[Package('framework')]
#[CoversClass(CheckExtensionsCommand::class)]
class CheckExtensionsCommandTest extends TestCase
{
    use ExtensionToolingCommandTestBehaviour;

    public function testCheckCommandRunsTheCheckEntryScript(): void
    {
        $administrationRoot = $this->createAdministrationRoot(withToolingStub: true);

        $tester = new CommandTester(new CheckExtensionsCommand($this->kernel(), $administrationRoot));
        $tester->execute(['tooling-args' => ['--only=MyPlugin']]);

        $capture = $this->readToolingCapture($administrationRoot);
        static::assertStringEndsWith('scripts/extensionTooling/check.ts', $capture['argv'][1]);
        static::assertContains('--only=MyPlugin', $capture['argv']);

        $this->removeAdministrationRoot($administrationRoot);
    }
}
