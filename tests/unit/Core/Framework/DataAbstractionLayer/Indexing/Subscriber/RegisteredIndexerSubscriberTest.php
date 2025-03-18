<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Indexing\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Infrastructure\Path\MediaPathPostUpdater;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\Subscriber\RegisteredIndexerSubscriber;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Store\Event\FirstRunWizardFinishedEvent;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;

/**
 * @internal
 */
#[CoversClass(RegisteredIndexerSubscriber::class)]
class RegisteredIndexerSubscriberTest extends TestCase
{
    public function testSendsMessage(): void
    {
        $productIndexer = $this->createMock(ProductIndexer::class);
        $productIndexer->method('getOptions')->willReturn(['seo', 'search', 'other-stuff']);

        $queuer = $this->createMock(IndexerQueuer::class);
        $queuer->expects($this->once())->method('getIndexers')->willReturn(['product.indexer' => ['seo']]);
        $queuer->expects($this->once())->method('finishIndexer')->with(['product.indexer']);

        $indexerRegistry = $this->createMock(EntityIndexerRegistry::class);
        $indexerRegistry->expects($this->once())->method('getIndexer')->with('product.indexer')->willReturn($productIndexer);
        $indexerRegistry->expects($this->once())->method('sendIndexingMessage')->with(['product.indexer'], ['search', 'other-stuff'], true);
        $indexerRegistry->expects($this->never())->method('index');

        $subscriber = new RegisteredIndexerSubscriber(
            $queuer,
            $indexerRegistry
        );
        $subscriber->runRegisteredIndexers();
    }

    public function testSendsMessageWithoutOptions(): void
    {
        $productIndexer = $this->createMock(ProductIndexer::class);
        $productIndexer->method('getOptions')->willReturn(['seo', 'search', 'other-stuff']);

        $queuer = $this->createMock(IndexerQueuer::class);
        $queuer->expects($this->once())->method('getIndexers')->willReturn(['product.indexer' => []]);
        $queuer->expects($this->once())->method('finishIndexer')->with(['product.indexer']);

        $indexerRegistry = $this->createMock(EntityIndexerRegistry::class);
        $indexerRegistry->expects($this->once())->method('getIndexer')->with('product.indexer')->willReturn($productIndexer);
        $indexerRegistry->expects($this->once())->method('sendIndexingMessage')->with(['product.indexer'], [], true);
        $indexerRegistry->expects($this->never())->method('index');

        $subscriber = new RegisteredIndexerSubscriber(
            $queuer,
            $indexerRegistry
        );
        $subscriber->runRegisteredIndexers();
    }

    public function testSendsMessageToSynchronousPostUpdaterIndexer(): void
    {
        $pathPostUpdater = $this->createMock(MediaPathPostUpdater::class);

        $queuer = $this->createMock(IndexerQueuer::class);
        $queuer->expects($this->once())->method('getIndexers')->willReturn(['media.path.post_update' => []]);
        $queuer->expects($this->once())->method('finishIndexer')->with(['media.path.post_update']);

        $indexerRegistry = $this->createMock(EntityIndexerRegistry::class);
        $indexerRegistry->expects($this->once())->method('getIndexer')->with('media.path.post_update')->willReturn($pathPostUpdater);
        $indexerRegistry->expects($this->never())->method('sendIndexingMessage');
        $indexerRegistry->expects($this->once())->method('index')->with(false, [], ['media.path.post_update'], true);

        $subscriber = new RegisteredIndexerSubscriber(
            $queuer,
            $indexerRegistry
        );
        $subscriber->runRegisteredIndexers();
    }

    public function testEmptyQueue(): void
    {
        $queuer = $this->createMock(IndexerQueuer::class);
        $queuer->expects($this->once())->method('getIndexers')->willReturn([]);
        $queuer->expects($this->never())->method('finishIndexer');

        $subscriber = new RegisteredIndexerSubscriber(
            $queuer,
            $this->createMock(EntityIndexerRegistry::class)
        );

        $subscriber->runRegisteredIndexers();
    }

    public function testIgnoresUnknownIndexer(): void
    {
        $productIndexer = $this->createMock(ProductIndexer::class);
        $productIndexer->method('getOptions')->willReturn(['seo', 'search', 'other-stuff']);

        $queuer = $this->createMock(IndexerQueuer::class);
        $queuer->expects($this->once())->method('getIndexers')->willReturn(['product.indexer' => ['seo'], 'unknown.indexer' => []]);
        $queuer->expects($this->once())->method('finishIndexer')->with(['product.indexer', 'unknown.indexer']);

        $indexerRegistry = $this->createMock(EntityIndexerRegistry::class);
        $indexerRegistry
            ->expects($this->exactly(2))
            ->method('getIndexer')
            ->willReturnCallback(static fn (string $name) => $name === 'product.indexer' ? $productIndexer : null);

        $indexerRegistry->expects($this->once())->method('sendIndexingMessage')->with(['product.indexer'], ['search', 'other-stuff']);

        $subscriber = new RegisteredIndexerSubscriber(
            $queuer,
            $indexerRegistry
        );

        $subscriber->runRegisteredIndexers();
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [
                UpdatePostFinishEvent::class => 'runRegisteredIndexers',
                FirstRunWizardFinishedEvent::class => 'runRegisteredIndexers',
            ],
            RegisteredIndexerSubscriber::getSubscribedEvents()
        );
    }
}
