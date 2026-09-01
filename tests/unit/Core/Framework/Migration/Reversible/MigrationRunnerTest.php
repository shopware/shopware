<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\Reversible;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Migration\Reversible\Migration;
use Shopware\Core\Framework\Migration\Reversible\MigrationProvider;
use Shopware\Core\Framework\Migration\Reversible\MigrationRunner;
use Shopware\Core\Framework\Migration\Reversible\MigrationStateStore;
use Shopware\Core\Framework\Plugin;
use Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\ImmediateMigrationLock;
use Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\InMemoryMigrationStateStore;
use Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\RecordingMigrationA;
use Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\RecordingMigrationB;
use Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\SwagReversible\SwagReversible;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MigrationRunner::class)]
class MigrationRunnerTest extends TestCase
{
    private const PLUGIN_NAME = 'SwagReversible';

    /**
     * @var \ArrayObject<int, string>
     */
    private \ArrayObject $calls;

    protected function setUp(): void
    {
        $this->calls = new \ArrayObject();
    }

    public function testUpAppliesPendingMigrationsInAscendingOrder(): void
    {
        $first = new RecordingMigrationA(100, $this->calls);
        $second = new RecordingMigrationB(200, $this->calls);

        $store = new InMemoryMigrationStateStore();
        $applied = $this->runner([$second, $first], $store)->up($this->plugin());

        static::assertSame(
            [RecordingMigrationA::class . '::up', RecordingMigrationB::class . '::up'],
            $this->calls->getArrayCopy()
        );
        static::assertSame([RecordingMigrationA::class, RecordingMigrationB::class], $applied);
        static::assertSame(
            [RecordingMigrationA::class => 100, RecordingMigrationB::class => 200],
            $store->timestampsFor(self::PLUGIN_NAME)
        );
    }

    public function testUpIsIdempotent(): void
    {
        $migration = new RecordingMigrationA(100, $this->calls);
        $runner = $this->runner([$migration], new InMemoryMigrationStateStore());

        $runner->up($this->plugin());
        $applied = $runner->up($this->plugin());

        static::assertSame([], $applied);
        static::assertCount(1, $this->calls);
    }

    public function testUpPassesTheInstallationFlagToTheContext(): void
    {
        $migration = new RecordingMigrationA(100, $this->calls);

        $this->runner([$migration], new InMemoryMigrationStateStore())->up($this->plugin(), true);

        static::assertNotNull($migration->lastUpContext);
        static::assertTrue($migration->lastUpContext->isInstallation);
    }

    public function testUpDefaultsTheInstallationFlagToFalse(): void
    {
        $migration = new RecordingMigrationA(100, $this->calls);

        $this->runner([$migration], new InMemoryMigrationStateStore())->up($this->plugin());

        static::assertNotNull($migration->lastUpContext);
        static::assertFalse($migration->lastUpContext->isInstallation);
    }

    public function testUpRejectsAMigrationInsertedBeforeTheLatestAppliedOne(): void
    {
        $inserted = new RecordingMigrationA(100, $this->calls);
        $applied = new RecordingMigrationB(200, $this->calls);

        $store = new InMemoryMigrationStateStore();
        $store->markExecuted(self::PLUGIN_NAME, RecordingMigrationB::class, 200);

        $this->expectExceptionObject(MigrationException::migrationOutOfOrder(
            self::PLUGIN_NAME,
            RecordingMigrationA::class,
            100,
            200
        ));

        $this->runner([$inserted, $applied], $store)->up($this->plugin());
    }

    public function testRejectsAnAppliedMigrationThatNoLongerExists(): void
    {
        $store = new InMemoryMigrationStateStore();
        $store->markExecuted(self::PLUGIN_NAME, RecordingMigrationB::class, 100);

        $this->expectExceptionObject(
            MigrationException::missingAppliedMigration(self::PLUGIN_NAME, RecordingMigrationB::class)
        );

        $this->runner([], $store)->up($this->plugin());
    }

    public function testRejectsAnAppliedMigrationWhoseTimestampChanged(): void
    {
        $migration = new RecordingMigrationA(200, $this->calls);

        $store = new InMemoryMigrationStateStore();
        $store->markExecuted(self::PLUGIN_NAME, RecordingMigrationA::class, 100);

        $this->expectExceptionObject(
            MigrationException::migrationTimestampChanged(RecordingMigrationA::class, 100, 200)
        );

        $this->runner([$migration], $store)->up($this->plugin());
    }

    public function testDownRollsBackInDescendingOrderAndClearsHistory(): void
    {
        $first = new RecordingMigrationA(100, $this->calls);
        $second = new RecordingMigrationB(200, $this->calls);

        $store = new InMemoryMigrationStateStore();
        $runner = $this->runner([$first, $second], $store);
        $runner->up($this->plugin());

        $this->calls->exchangeArray([]);
        $removed = $runner->down($this->plugin());

        static::assertSame(
            [RecordingMigrationB::class . '::down', RecordingMigrationA::class . '::down'],
            $this->calls->getArrayCopy()
        );
        static::assertSame([RecordingMigrationB::class, RecordingMigrationA::class], $removed);
        static::assertSame([], $store->timestampsFor(self::PLUGIN_NAME));
    }

    public function testDownKeepsSchemaAndHistoryWhenUserDataIsKept(): void
    {
        $migration = new RecordingMigrationA(100, $this->calls);

        $store = new InMemoryMigrationStateStore();
        $runner = $this->runner([$migration], $store);
        $runner->up($this->plugin());

        $this->calls->exchangeArray([]);
        $removed = $runner->down($this->plugin(), true);

        static::assertSame([], $removed);
        static::assertCount(0, $this->calls);
        static::assertSame([RecordingMigrationA::class => 100], $store->timestampsFor(self::PLUGIN_NAME));
    }

    public function testDownPassesKeepUserDataToTheContext(): void
    {
        $migration = new RecordingMigrationA(100, $this->calls);

        $runner = $this->runner([$migration], new InMemoryMigrationStateStore());
        $runner->up($this->plugin());
        $runner->down($this->plugin());

        static::assertNotNull($migration->lastDownContext);
        static::assertFalse($migration->lastDownContext->keepUserData);
    }

    public function testWorkIsSynchronizedPerPlugin(): void
    {
        $lock = new ImmediateMigrationLock();
        $runner = new MigrationRunner(
            $this->provider([]),
            new InMemoryMigrationStateStore(),
            $lock,
            static::createStub(Connection::class)
        );

        $runner->up($this->plugin());
        $runner->down($this->plugin());

        static::assertSame([self::PLUGIN_NAME, self::PLUGIN_NAME], $lock->acquired);
    }

    /**
     * @param list<Migration> $migrations
     */
    private function runner(array $migrations, MigrationStateStore $store): MigrationRunner
    {
        return new MigrationRunner(
            $this->provider($migrations),
            $store,
            new ImmediateMigrationLock(),
            static::createStub(Connection::class)
        );
    }

    /**
     * @param list<Migration> $migrations
     */
    private function provider(array $migrations): MigrationProvider
    {
        // the real provider guarantees ascending order, so the fake must too
        usort($migrations, static fn (Migration $a, Migration $b): int => $a->getCreationTimestamp() <=> $b->getCreationTimestamp());

        $provider = static::createStub(MigrationProvider::class);
        $provider->method('forPlugin')->willReturn($migrations);

        return $provider;
    }

    private function plugin(): Plugin
    {
        $directory = \dirname((string) (new \ReflectionClass(SwagReversible::class))->getFileName());

        return new SwagReversible(true, $directory);
    }
}
