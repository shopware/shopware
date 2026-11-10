<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Command\UpgradeExtensionsCommand;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Output\OutputInterface;
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
        $command = new class($this->kernel(), __DIR__ . '/_fixtures/administration-root') extends UpgradeExtensionsCommand {
            /**
             * @var list<string>|null
             */
            public ?array $spawned = null;

            protected function runTooling(array $command, string $cwd, array $env, OutputInterface $output): int
            {
                $this->spawned = $command;

                return self::SUCCESS;
            }
        };

        (new CommandTester($command))->execute(['tooling-args' => ['--only=MyPlugin', '--write']]);

        static::assertNotNull($command->spawned);
        static::assertStringEndsWith('scripts/extensionTooling/upgrade.ts', $command->spawned[2]);
        static::assertContains('--only=MyPlugin', $command->spawned);
        static::assertContains('--write', $command->spawned);
    }

    private function kernel(): KernelInterface
    {
        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn('/shop');

        return $kernel;
    }
}
