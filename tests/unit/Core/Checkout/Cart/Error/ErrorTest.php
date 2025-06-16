<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Error;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Address\Error\AddressValidationError;
use Shopware\Core\Checkout\Cart\Address\Error\BillingAddressBlockedError;
use Shopware\Core\Checkout\Cart\Address\Error\BillingAddressCountryRegionMissingError;
use Shopware\Core\Checkout\Cart\Address\Error\BillingAddressSalutationMissingError;
use Shopware\Core\Checkout\Cart\Address\Error\ShippingAddressBlockedError;
use Shopware\Core\Checkout\Cart\Address\Error\ShippingAddressCountryRegionMissingError;
use Shopware\Core\Checkout\Cart\Address\Error\ShippingAddressSalutationMissingError;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\GenericCartError;
use Shopware\Core\Checkout\Cart\Error\IncompleteLineItemError;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Gateway\Error\CheckoutGatewayError;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\Checkout\Promotion\Cart\Error\AutoPromotionNotFoundError;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionExcludedError;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionNotEligibleError;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionNotFoundError;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionsOnCartPriceZeroError;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCartAddedInformationError;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCartDeletedInformationError;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Content\Product\Cart\MinOrderQuantityError;
use Shopware\Core\Content\Product\Cart\ProductNotFoundError;
use Shopware\Core\Content\Product\Cart\ProductOutOfStockError;
use Shopware\Core\Content\Product\Cart\ProductStockReachedError;
use Shopware\Core\Content\Product\Cart\PurchaseStepsError;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Checkout\Cart\Error\PaymentMethodChangedError;
use Shopware\Storefront\Checkout\Cart\Error\ShippingMethodChangedError;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[CoversClass(Error::class)]
#[Package('checkout')]
class ErrorTest extends TestCase
{
    public function testShippingMethodBlockedErrorSerialization(): void
    {
        $error = new ShippingMethodBlockedError('foo');

        static::assertSame('foo', $error->getName());

        $serialized = serialize($error);

        $unserialized = unserialize($serialized);
        static::assertInstanceOf(ShippingMethodBlockedError::class, $unserialized);

        static::assertSame('foo', $unserialized->getName());
    }

    #[DataProvider('serializationDataProvider')]
    public function testErrorSerialization(Error $error): void
    {
        $serialized = serialize($error);

        $unserialized = unserialize($serialized);
        static::assertInstanceOf($error::class, $unserialized);

        // Call all public methods without parameters (i.e. getters) to make sure the don't throw an exception
        $refClass = new \ReflectionClass($error);
        $refMethods = $refClass->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($refMethods as $method) {
            if ($method->getNumberOfParameters() !== 0) {
                continue;
            }

            $method->invoke($error);
        }
    }

    /**
     * @return iterable<class-string<Error>, array{0: Error}>
     */
    public static function serializationDataProvider(): iterable
    {
        yield AddressValidationError::class => [new AddressValidationError(true, new ConstraintViolationList())];
        yield BillingAddressBlockedError::class => [new BillingAddressBlockedError('foo')];
        yield BillingAddressCountryRegionMissingError::class => [new BillingAddressCountryRegionMissingError(self::createCustomerAddress())];
        yield BillingAddressSalutationMissingError::class => [new BillingAddressSalutationMissingError(self::createCustomerAddress())];
        yield ShippingAddressBlockedError::class => [new ShippingAddressBlockedError('foo')];
        yield ShippingAddressCountryRegionMissingError::class => [new ShippingAddressCountryRegionMissingError(self::createCustomerAddress())];
        yield ShippingAddressSalutationMissingError::class => [new ShippingAddressSalutationMissingError(self::createCustomerAddress())];
        yield GenericCartError::class => [new GenericCartError('foo', 'bar', [], Error::LEVEL_ERROR, false, false, false)];
        yield IncompleteLineItemError::class => [new IncompleteLineItemError('foo', 'bar')];
        yield CheckoutGatewayError::class => [new CheckoutGatewayError('foo', Error::LEVEL_NOTICE, true)];
        yield PaymentMethodBlockedError::class => [new PaymentMethodBlockedError('foo', 'reason')];
        yield AutoPromotionNotFoundError::class => [new AutoPromotionNotFoundError('foo')];
        yield PromotionExcludedError::class => [new PromotionExcludedError('foo')];
        yield PromotionNotEligibleError::class => [new PromotionNotEligibleError('foo')];
        yield PromotionNotFoundError::class => [new PromotionNotFoundError('foo')];
        yield PromotionsOnCartPriceZeroError::class => [new PromotionsOnCartPriceZeroError(['foo', 'bar'])];
        yield PromotionCartAddedInformationError::class => [new PromotionCartAddedInformationError(self::createLineItem())];
        yield PromotionCartDeletedInformationError::class => [new PromotionCartDeletedInformationError(self::createLineItem())];
        yield ShippingMethodBlockedError::class => [new ShippingMethodBlockedError('foo')];
        yield MinOrderQuantityError::class => [new MinOrderQuantityError(Uuid::randomHex(), 'foo', 5)];
        yield ProductNotFoundError::class => [new ProductNotFoundError(Uuid::randomHex())];
        yield ProductOutOfStockError::class => [new ProductOutOfStockError(Uuid::randomHex(), 'foo')];
        yield ProductStockReachedError::class => [new ProductStockReachedError(Uuid::randomHex(), 'foo', 1)];
        yield PurchaseStepsError::class => [new PurchaseStepsError(Uuid::randomHex(), 'foo', 5)];
        yield PaymentMethodChangedError::class => [new PaymentMethodChangedError('foo', 'bar')];
        yield ShippingMethodChangedError::class => [new ShippingMethodChangedError('foo', 'bar')];
    }

    public function testAllErrorsCovered(): void
    {
        $testedErrors = \array_keys(\iterator_to_array(self::serializationDataProvider()));

        $classLoader = require __DIR__ . '/../../../../../../vendor/autoload.php';
        static::assertInstanceOf(ClassLoader::class, $classLoader);

        $loadedErrors = [];

        foreach ($classLoader->getClassMap() as $class => $_) {
            if (!str_starts_with($class, 'Shopware\\')) {
                continue;
            }

            if ($class !== Error::class && !\is_subclass_of($class, Error::class)) {
                continue;
            }

            $refClass = new \ReflectionClass($class);
            if ($refClass->isAbstract()) {
                continue;
            }

            $loadedErrors[] = $class;
        }

        if (empty($loadedErrors)) {
            static::fail('composer autoloader has not been optimized. Run `composer dump-autoload --optimize` to fix this.');
        }

        $missing = array_diff($loadedErrors, $testedErrors);
        static::assertEmpty(
            $missing,
            'The following cart errors have not been added to the serialization Test: ' . \implode(', ', $missing),
        );
    }

    private static function createCustomerAddress(): CustomerAddressEntity
    {
        $address = new CustomerAddressEntity();
        $address->setId(Uuid::randomHex());

        $address->setCustomerId(Uuid::randomHex());
        $address->setCountryId(Uuid::randomHex());
        $address->setFirstName('John');
        $address->setLastName('Doe');
        $address->setZipcode('12345');
        $address->setCity('Testcity');
        $address->setStreet('Teststreet 1');

        return $address;
    }

    private static function createLineItem(): LineItem
    {
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 2);
        $lineItem->setLabel('LineItem label');

        return $lineItem;
    }
}
