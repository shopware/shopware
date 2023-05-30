<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartSerializationCleaner;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Event\CartBeforeSerializationEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EventDispatcherBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\System\Country\CountryEntity;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
class CartSerializationCleanerTest extends TestCase
{
    use EventDispatcherBehaviour;
    use KernelTestBehaviour;

    /**
     * @dataProvider cleanupCustomFieldsProvider
     *
     * @param array<string, mixed> $payloads
     * @param array<string> $allowed
     */
    public function testLineItemCustomFields(Cart $cart, array $payloads = [], array $allowed = []): void
    {
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');

        $this->addEventListener($dispatcher, CartBeforeSerializationEvent::class, $listener);

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())->method('fetchFirstColumn')->willReturn($allowed);

        $cleaner = new CartSerializationCleaner($connection, $dispatcher);
        $cleaner->cleanupCart($cart);

        $items = $cart->getLineItems()->getFlat();
        foreach ($items as $item) {
            static::assertArrayHasKey($item->getId(), $payloads);
            static::assertEquals($payloads[$item->getId()], $item->getPayload());
        }

        $delivery = $cart->getDeliveries()->first();
        $deliveryItems = $delivery !== null ? $delivery->getPositions()->getLineItems()->getFlat() : [];

        foreach ($deliveryItems as $item) {
            static::assertArrayHasKey($item->getId(), $payloads);
            static::assertEquals($payloads[$item->getId()], $item->getPayload());
        }
    }

    /**
     * @dataProvider cleanupCoversProvider
     */
    public function testLineItemCovers(Cart $cart, ?MediaEntity $expectedCover): void
    {
        $dispatcher = $this->createMock(EventDispatcher::class);
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())->method('fetchFirstColumn');

        $cleaner = new CartSerializationCleaner($connection, $dispatcher);
        $cleaner->cleanupCart($cart);

        $items = $cart->getLineItems()->getFlat();
        foreach ($items as $item) {
            static::assertEquals($expectedCover, $item->getCover());
        }
    }

    public static function cleanupCustomFieldsProvider(): \Generator
    {
        yield 'Test empty cart' => [
            new Cart('test'),
            [],
        ];

        yield 'Test strip payload' => [
            self::payloadCart('foo', ['customFields' => ['bar' => 1]]),
            ['foo' => ['customFields' => []], 'foo-child' => ['customFields' => []]],
        ];

        yield 'Test allowed field' => [
            self::payloadCart('foo', ['customFields' => ['bar' => 1]]),
            ['foo' => ['customFields' => ['bar' => 1]], 'foo-child' => ['customFields' => ['bar' => 1]]],
            ['bar'],
        ];

        yield 'Test multiple allowed fields' => [
            self::payloadCart('foo', ['customFields' => ['bar' => 1, 'baz' => 2]]),
            ['foo' => ['customFields' => ['bar' => 1, 'baz' => 2]], 'foo-child' => ['customFields' => ['bar' => 1, 'baz' => 2]]],
            ['bar', 'baz'],
        ];

        yield 'Test allowed field with unkown key' => [
            self::payloadCart('foo', ['customFields' => ['bar' => 1]]),
            ['foo' => ['customFields' => []], 'foo-child' => ['customFields' => []]],
            ['unknown_field'],
        ];
    }

    public static function cleanupCoversProvider(): \Generator
    {
        yield 'Test cover thumbnailRo cleanup' => [
            self::coverCart('foo', 'test'),
            (self::coverItem('foo', ''))->getCover(),
        ];

        yield 'Test cover thumbnailRo cleanup without ro data' => [
            self::coverCart('foo', null),
            (self::coverItem('foo', null))->getCover(),
        ];

        yield 'Test cover thumbnailRo cleanup without cover' => [
            self::coverCart('foo', null, true),
            null,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function payloadItem(string $id, array $payload): LineItem
    {
        $item = new LineItem($id, 'foo');
        $item->setPayload($payload);

        $childItem = new LineItem($id . '-child', 'foo');
        $childItem->setPayload($payload);

        $item->addChild($childItem);

        return $item;
    }

    private static function coverItem(string $id, ?string $thumbnailString, bool $skipCover = false): LineItem
    {
        $item = new LineItem($id, 'foo');
        $childItem = new LineItem($id . 'child', 'foo');

        $item->addChild($childItem);

        if ($skipCover === true) {
            return $item;
        }

        $cover = new MediaEntity();
        if ($thumbnailString !== null) {
            $cover->setThumbnailsRo($thumbnailString);
        }

        $item->setCover($cover);

        $coverChild = new MediaEntity();
        if ($thumbnailString !== null) {
            $coverChild->setThumbnailsRo($thumbnailString);
        }

        $childItem->setCover($cover);

        return $item;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function payloadCart(string $id, array $payload): Cart
    {
        $cart = (new Cart('test'))->add(self::payloadItem($id, $payload));
        $cart->addDeliveries(self::itemDelivery(self::payloadItem($id, $payload)));

        return $cart;
    }

    private static function coverCart(string $id, ?string $thumbnailString, bool $skipCover = false): Cart
    {
        $cart = (new Cart('test'))->add(self::coverItem($id, $thumbnailString, $skipCover));
        $cart->addDeliveries(self::itemDelivery(self::coverItem($id, $thumbnailString, $skipCover)));

        return $cart;
    }

    private static function itemDelivery(LineItem $lineItem): DeliveryCollection
    {
        $delivery = new Delivery(
            new DeliveryPositionCollection(
                [
                    new DeliveryPosition(
                        $lineItem->getId(),
                        $lineItem,
                        1,
                        new CalculatedPrice(1.0, 1.0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable())
                    ),
                ]
            ),
            new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
            new ShippingMethodEntity(),
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(1.0, 1.0, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        return new DeliveryCollection([$delivery]);
    }
}
