<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Shopware\Elasticsearch\Admin\Subscriber\ProductInheritanceSubscriber;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductInheritanceSubscriber::class)]
class ProductInheritanceSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame([ProductIndexerEvent::class => 'update'], ProductInheritanceSubscriber::getSubscribedEvents());
    }

    public function testProductsWhoseInheritanceWasUpdatedAreIndexed(): void
    {
        $context = Context::createDefaultContext();
        $registry = $this->createMock(AdminSearchRegistry::class);
        $registry->expects($this->once())->method('updateIds')->with('product', ['variant-1', 'variant-2'], $context);

        (new ProductInheritanceSubscriber($registry))->update(new ProductIndexerEvent(['variant-1', 'variant-2'], $context, [ProductIndexer::STOCK_UPDATER]));
    }

    public function testProductsWhoseInheritanceWasSkippedAreNotIndexedAgain(): void
    {
        $registry = $this->createMock(AdminSearchRegistry::class);
        $registry->expects($this->never())->method('updateIds');

        (new ProductInheritanceSubscriber($registry))->update(new ProductIndexerEvent(['product-1'], Context::createDefaultContext(), [ProductIndexer::INHERITANCE_UPDATER]));
    }
}
