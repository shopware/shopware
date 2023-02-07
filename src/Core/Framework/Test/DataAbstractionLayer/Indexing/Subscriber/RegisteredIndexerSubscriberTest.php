<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Indexing\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\Subscriber\RegisteredIndexerSubscriber;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Store\Event\FirstRunWizardFinishedEvent;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;

/**
 * @internal
 */
class RegisteredIndexerSubscriberTest extends TestCase
{
    public function testSendsMessage(): void
    {
        $productIndexer = $this->createMock(ProductIndexer::class);
        $productIndexer->method('getOptions')->willReturn(['seo', 'search', 'other-stuff']);

        $queuer = $this->createMock(IndexerQueuer::class);
        $queuer->expects(static::once())->method('getIndexers')->willReturn(['product.indexer' => ['seo']]);
        $queuer->expects(static::once())->method('finishIndexer')->with(['product.indexer']);

        $indexerRegistery = $this->createMock(EntityIndexerRegistry::class);
        $indexerRegistery->expects(static::once())->method('getIndexer')->with('product.indexer')->willReturn($productIndexer);
        $indexerRegistery->expects(static::once())->method('sendIndexingMessage')->with(['product.indexer'], ['search', 'other-stuff']);

        $subscriber = new RegisteredIndexerSubscriber(
            $queuer,
            $indexerRegistery
        );
        $subscriber->runRegisteredIndexers();
    }

    public function testSendsMessageWithoutOptions(): void
    {
        $productIndexer = $this->createMock(ProductIndexer::class);
        $productIndexer->method('getOptions')->willReturn(['seo', 'search', 'other-stuff']);

        $queuer = $this->createMock(IndexerQueuer::class);
        $queuer->expects(static::once())->method('getIndexers')->willReturn(['product.indexer' => []]);
        $queuer->expects(static::once())->method('finishIndexer')->with(['product.indexer']);

        $indexerRegistery = $this->createMock(EntityIndexerRegistry::class);
        $indexerRegistery->expects(static::once())->method('getIndexer')->with('product.indexer')->willReturn($productIndexer);
        $indexerRegistery->expects(static::once())->method('sendIndexingMessage')->with(['product.indexer'], []);

        $subscriber = new RegisteredIndexerSubscriber(
            $queuer,
            $indexerRegistery
        );
        $subscriber->runRegisteredIndexers();
    }

    public function testEmptyQueue(): void
    {
        $queuer = $this->createMock(IndexerQueuer::class);
        $queuer->expects(static::once())->method('getIndexers')->willReturn([]);
        $queuer->expects(static::never())->method('finishIndexer');

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
        $queuer->expects(static::once())->method('getIndexers')->willReturn(['product.indexer' => ['seo'], 'unknown.indexer' => []]);
        $queuer->expects(static::once())->method('finishIndexer')->with(['product.indexer', 'unknown.indexer']);

        $indexerRegistery = $this->createMock(EntityIndexerRegistry::class);
        $indexerRegistery
            ->expects(static::exactly(2))
            ->method('getIndexer')
            ->willReturnCallback(static fn (string $name) => $name === 'product.indexer' ? $productIndexer : null);

        $indexerRegistery->expects(static::once())->method('sendIndexingMessage')->with(['product.indexer'], ['search', 'other-stuff']);

        $subscriber = new RegisteredIndexerSubscriber(
            $queuer,
            $indexerRegistery
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
