<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\Reversible\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Migration\Reversible\Command\ReversibleMigrationCommand;
use Shopware\Core\Framework\Migration\Reversible\MigrationRunner;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\SwagNoMigrations\SwagNoMigrations;
use Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\SwagNotInstantiable\SwagNotInstantiable;
use Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\SwagReversible\SwagReversible;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ReversibleMigrationCommand::class)]
class ReversibleMigrationCommandTest extends TestCase
{
    public function testMigratesASinglePlugin(): void
    {
        $runner = $this->createMock(MigrationRunner::class);
        $runner->expects($this->once())
            ->method('up')
            ->willReturn(['Acme\\Migration1']);

        $tester = new CommandTester(new ReversibleMigrationCommand($runner, $this->plugins(SwagReversible::class)));
        $tester->execute(['plugin' => 'SwagReversible']);

        $tester->assertCommandIsSuccessful();
        static::assertStringContainsString('Acme\\Migration1', $tester->getDisplay());
    }

    public function testReportsAPluginThatIsAlreadyUpToDate(): void
    {
        $runner = static::createStub(MigrationRunner::class);
        $runner->method('up')->willReturn([]);

        $tester = new CommandTester(new ReversibleMigrationCommand($runner, $this->plugins(SwagReversible::class)));
        $tester->execute(['plugin' => 'SwagReversible']);

        static::assertStringContainsString('SwagReversible is already up to date.', $tester->getDisplay());
    }

    public function testResolvesAPluginByUniquePrefix(): void
    {
        $runner = $this->createMock(MigrationRunner::class);
        $runner->expects($this->once())->method('up')->willReturn([]);

        $tester = new CommandTester(new ReversibleMigrationCommand($runner, $this->plugins(SwagReversible::class)));
        $tester->execute(['plugin' => 'SwagRev']);

        $tester->assertCommandIsSuccessful();
    }

    public function testFailsForAnUnknownPlugin(): void
    {
        $command = new ReversibleMigrationCommand(
            static::createStub(MigrationRunner::class),
            $this->plugins(SwagReversible::class)
        );

        $this->expectExceptionObject(MigrationException::pluginNotFound('Nope'));

        (new CommandTester($command))->execute(['plugin' => 'Nope']);
    }

    public function testFailsForAnAmbiguousPluginPrefix(): void
    {
        $command = new ReversibleMigrationCommand(
            static::createStub(MigrationRunner::class),
            $this->plugins(SwagNoMigrations::class, SwagNotInstantiable::class)
        );

        $this->expectExceptionObject(MigrationException::moreThanOnePluginFound(
            'SwagNo',
            [SwagNoMigrations::class, SwagNotInstantiable::class]
        ));

        (new CommandTester($command))->execute(['plugin' => 'SwagNo']);
    }

    public function testFailsWhenNeitherPluginNorAllIsGiven(): void
    {
        $command = new ReversibleMigrationCommand(
            static::createStub(MigrationRunner::class),
            $this->plugins(SwagReversible::class)
        );

        $this->expectExceptionObject(
            MigrationException::invalidArgument('Missing plugin name or --all option.')
        );

        (new CommandTester($command))->execute([]);
    }

    public function testFailsWhenBothPluginAndAllAreGiven(): void
    {
        $command = new ReversibleMigrationCommand(
            static::createStub(MigrationRunner::class),
            $this->plugins(SwagReversible::class)
        );

        $this->expectExceptionObject(
            MigrationException::invalidArgument('Pass either a plugin name or the --all option, not both.')
        );

        (new CommandTester($command))->execute(['plugin' => 'SwagReversible', '--all' => true]);
    }

    public function testAllOnlyProcessesActivePluginsInSortedOrder(): void
    {
        $active = $this->plugin(SwagReversible::class, true);
        $inactive = $this->plugin(SwagNoMigrations::class, false);

        $processed = [];
        $runner = static::createStub(MigrationRunner::class);
        $runner->method('up')->willReturnCallback(function (Plugin $plugin) use (&$processed): array {
            $processed[] = $plugin->getName();

            return [];
        });

        $collection = new KernelPluginCollection();
        $collection->addList([$inactive, $active]);
        $tester = new CommandTester(new ReversibleMigrationCommand($runner, $collection));
        $tester->execute(['--all' => true]);

        $tester->assertCommandIsSuccessful();
        static::assertSame(['SwagReversible'], $processed);
    }

    /**
     * @param class-string<Plugin> ...$classes
     */
    private function plugins(string ...$classes): KernelPluginCollection
    {
        $collection = new KernelPluginCollection();
        foreach ($classes as $class) {
            $collection->add($this->plugin($class, true));
        }

        return $collection;
    }

    /**
     * @param class-string<Plugin> $class
     */
    private function plugin(string $class, bool $active): Plugin
    {
        $directory = \dirname((string) (new \ReflectionClass($class))->getFileName());

        return new $class($active, $directory);
    }
}
