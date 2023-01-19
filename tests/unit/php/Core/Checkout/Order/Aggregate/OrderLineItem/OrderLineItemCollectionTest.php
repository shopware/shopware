<?php declare(strict_types=1);

namespace unit\php\Core\Checkout\Order\Aggregate\OrderLineItem;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection
 */
class OrderLineItemCollectionTest extends TestCase
{
    public function testFilterGoodsFlat(): void
    {
        $lineItemA = new OrderLineItemEntity();
        $lineItemA->setId(Uuid::randomHex());
        $lineItemA->setGood(true);

        $lineItemB = new OrderLineItemEntity();
        $lineItemB->setId(Uuid::randomHex());
        $lineItemB->setGood(false);

        $lineItemC = new OrderLineItemEntity();
        $lineItemC->setId(Uuid::randomHex());
        $lineItemC->setGood(true);

        $lineItemD = new OrderLineItemEntity();
        $lineItemD->setId(Uuid::randomHex());
        $lineItemD->setGood(false);

        $lineItemE = new OrderLineItemEntity();
        $lineItemE->setId(Uuid::randomHex());
        $lineItemE->setGood(true);

        $lineItemC->setChildren(new OrderLineItemCollection([$lineItemE, $lineItemD]));

        $collection = new OrderLineItemCollection([$lineItemA, $lineItemB, $lineItemC]);

        $filtered = $collection->filterGoodsFlat();

        static::assertEquals([$lineItemA, $lineItemC, $lineItemE], $filtered);
    }

    public function testGetPayloadsProperty(): void
    {
        $lineItemA = new OrderLineItemEntity();
        $lineItemA->setId(Uuid::randomHex());

        $lineItemB = new OrderLineItemEntity();
        $lineItemB->setId(Uuid::randomHex());

        $collection = new OrderLineItemCollection([$lineItemA, $lineItemB]);

        static::assertEquals([], $collection->getPayloadsProperty('foobar'));

        $lineItemA->setPayload(['foobar' => 'foo']);

        static::assertEquals([$lineItemA->getId() => 'foo'], $collection->getPayloadsProperty('foobar'));

        $lineItemB->setPayload(['foobar' => 'bar']);

        static::assertEquals([$lineItemA->getId() => 'foo', $lineItemB->getId() => 'bar'], $collection->getPayloadsProperty('foobar'));
    }
}
