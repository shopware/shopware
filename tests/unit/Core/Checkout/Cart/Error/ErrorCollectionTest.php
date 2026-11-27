<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Error\GenericCartError;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ErrorCollection::class)]
class ErrorCollectionTest extends TestCase
{
    public function testHashing(): void
    {
        $collection = new ErrorCollection();

        static::assertSame('', $collection->getUniqueHash());

        $collection->add(new GenericCartError('12', 'asd', [], Error::LEVEL_ERROR, false, false, false));

        static::assertSame('8412c377d151321a', $collection->getUniqueHash());
    }

    public function testBlockOrderWhenAnyErrorBlocksTheOrder(): void
    {
        $collection = new ErrorCollection([
            new GenericCartError('harmless', 'harmless', [], Error::LEVEL_NOTICE, false, false, false),
        ]);

        static::assertFalse($collection->blockOrder());

        $collection->add(new GenericCartError('blocking', 'blocking', [], Error::LEVEL_ERROR, true, false, false));

        static::assertTrue($collection->blockOrder());
    }

    public function testHasLevel(): void
    {
        $collection = new ErrorCollection([
            new GenericCartError('notice', 'notice', [], Error::LEVEL_NOTICE, false, false, false),
        ]);

        static::assertTrue($collection->hasLevel(Error::LEVEL_NOTICE));
        static::assertFalse($collection->hasLevel(Error::LEVEL_ERROR));
    }

    public function testGetPersistentReturnsOnlyPersistentErrors(): void
    {
        $collection = new ErrorCollection([
            new GenericCartError('persistent', 'persistent', [], Error::LEVEL_ERROR, false, true, false),
            new GenericCartError('transient', 'transient', [], Error::LEVEL_ERROR, false, false, false),
        ]);

        $persistent = $collection->getPersistent();

        static::assertCount(1, $persistent);
        static::assertSame('persistent', $persistent->first()?->getId());
    }

    /**
     * @param list<string> $first
     * @param list<string> $second
     * @param list<string> $expected
     */
    #[DataProvider('blockedDeliveriesProvider')]
    public function testBlockedShippingMethodErrorsOfTheSameMethodKeepEveryDelivery(array $first, array $second, array $expected): void
    {
        $collection = new ErrorCollection();
        $collection->add(new ShippingMethodBlockedError(name: 'Express', id: 'express-id', reason: 'no shipping costs found', deliveryIdentifiers: $first));
        $collection->add(new ShippingMethodBlockedError(name: 'Express', id: 'express-id', reason: 'rule not matching or inactive', deliveryIdentifiers: $second));

        $error = $collection->get('shipping-method-blocked-express-id');
        static::assertCount(1, $collection);
        static::assertInstanceOf(ShippingMethodBlockedError::class, $error);
        static::assertSame('rule not matching or inactive', $error->getReason());
        static::assertSame($expected, $error->getDeliveryIdentifiers());
    }

    /**
     * @return iterable<string, array{list<string>, list<string>, list<string>}>
     */
    public static function blockedDeliveriesProvider(): iterable
    {
        yield 'different deliveries' => [['warehouse-1'], ['warehouse-2'], ['warehouse-1', 'warehouse-2']];
        yield 'overlapping deliveries' => [['warehouse-1', 'warehouse-2'], ['warehouse-2', 'warehouse-3'], ['warehouse-1', 'warehouse-2', 'warehouse-3']];
        yield 'later error without deliveries' => [['warehouse-1'], [], ['warehouse-1']];
        yield 'earlier error without deliveries' => [[], ['warehouse-2'], ['warehouse-2']];
    }

    public function testApiAlias(): void
    {
        static::assertSame('cart_error_collection', (new ErrorCollection())->getApiAlias());
    }
}
