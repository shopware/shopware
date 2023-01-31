<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopware\Elasticsearch\Product\ProductUpdater;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Product\ProductUpdater
 */
class ProductUpdaterTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame([ProductIndexerEvent::class => 'update'], ProductUpdater::getSubscribedEvents());
    }

    public function testUpdate(): void
    {
        $indexer = $this->createMock(ElasticsearchIndexer::class);
        $definition = $this->createMock(EntityDefinition::class);

        $indexer->expects(static::once())->method('updateIds')->with($definition, ['id1', 'id2']);

        $event = new ProductIndexerEvent(['id1', 'id2'], Context::createDefaultContext());

        $updater = new ProductUpdater($indexer, $definition);
        $updater->update($event);
    }
}
