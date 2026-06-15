<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductListingResolvePreviewEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductListingResolvePreviewEvent::class)]
class ProductListingResolvePreviewEventTest extends TestCase
{
    public function testReplace(): void
    {
        $event = new ProductListingResolvePreviewEvent(
            $this->createMock(SalesChannelContext::class),
            new Criteria(),
            ['p1' => 'p1'],
            true
        );

        $event->replace('p1', 'p2');
        static::assertSame(['p1' => 'p2'], $event->getMapping());
    }

    public function testReplaceException(): void
    {
        $event = new ProductListingResolvePreviewEvent(
            $this->createMock(SalesChannelContext::class),
            new Criteria(),
            ['p1' => 'p1'],
            true
        );

        static::expectException(\RuntimeException::class);
        $event->replace('p3', 'p2');
    }
}
