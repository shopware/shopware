<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Aggregate\OrderLineItemDownload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadEntity;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(OrderLineItemDownloadCollection::class)]
class OrderLineItemDownloadCollectionTest extends TestCase
{
    public function testFilterByOrderLineItemId(): void
    {
        $filterId = Uuid::randomHex();

        $downloadA = new OrderLineItemDownloadEntity();
        $downloadA->setId(Uuid::randomHex());
        $downloadA->setOrderLineItemId(Uuid::randomHex());

        $downloadB = new OrderLineItemDownloadEntity();
        $downloadB->setId(Uuid::randomHex());
        $downloadB->setOrderLineItemId(Uuid::randomHex());

        $collection = new OrderLineItemDownloadCollection([$downloadA, $downloadB]);

        static::assertEquals(0, $collection->filterByOrderLineItemId($filterId)->count());

        $downloadA->setOrderLineItemId($filterId);

        static::assertEquals(1, $collection->filterByOrderLineItemId($filterId)->count());
    }
}
