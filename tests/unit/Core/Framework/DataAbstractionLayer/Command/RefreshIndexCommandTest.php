<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Command\RefreshIndexCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Event\RefreshIndexEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RefreshIndexCommand::class)]
class RefreshIndexCommandTest extends TestCase
{
    #[TestDox('All indexers run synchronously by default and a RefreshIndexEvent is dispatched')]
    public function testRefreshesAllIndexersSynchronouslyByDefault(): void
    {
        $registry = $this->createMock(EntityIndexerRegistry::class);
        $registry->expects($this->once())->method('index')->with(false, [], []);

        $dispatchedEvents = [];
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(RefreshIndexEvent::class, function (RefreshIndexEvent $event) use (&$dispatchedEvents): void {
            $dispatchedEvents[] = $event;
        });

        $commandTester = new CommandTester(new RefreshIndexCommand($registry, $eventDispatcher));

        static::assertSame(Command::SUCCESS, $commandTester->execute([]));

        static::assertCount(1, $dispatchedEvents);
        static::assertTrue($dispatchedEvents[0]->getNoQueue());
    }

    #[TestDox('The skip and only options are parsed into indexer lists and stripped to entity names for the event')]
    public function testPassesSkipAndOnlyListsToRegistryAndEvent(): void
    {
        $registry = $this->createMock(EntityIndexerRegistry::class);
        $registry
            ->expects($this->once())
            ->method('index')
            ->with(true, ['product.indexer', 'category.indexer'], ['media.indexer']);

        $dispatchedEvents = [];
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(RefreshIndexEvent::class, function (RefreshIndexEvent $event) use (&$dispatchedEvents): void {
            $dispatchedEvents[] = $event;
        });

        $commandTester = new CommandTester(new RefreshIndexCommand($registry, $eventDispatcher));

        static::assertSame(Command::SUCCESS, $commandTester->execute([
            '--use-queue' => true,
            '--skip' => 'product.indexer,category.indexer',
            '--only' => 'media.indexer',
        ]));

        static::assertCount(1, $dispatchedEvents);
        static::assertFalse($dispatchedEvents[0]->getNoQueue());
        static::assertSame(['product', 'category'], $dispatchedEvents[0]->getSkipEntities());
        static::assertSame(['media'], $dispatchedEvents[0]->getOnlyEntities());
    }
}
