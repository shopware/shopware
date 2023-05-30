<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Shipping\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryValidator;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
class ShippingMethodValidatorTest extends TestCase
{
    public function testValidateWithEmptyCart(): void
    {
        $cart = new Cart('test');

        $validator = new DeliveryValidator();
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $this->createMock(SalesChannelContext::class));

        static::assertCount(0, $errors);
    }

    public function testValidateWithoutRules(): void
    {
        $deliveryTime = $this->generateDeliveryTimeDummy();

        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('1');
        $shippingMethod->setAvailabilityRuleId('1');
        $shippingMethod->setDeliveryTime($deliveryTime);
        $shippingMethod->setActive(true);
        $deliveryDate = new DeliveryDate(new \DateTime(), new \DateTime());
        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            $deliveryDate,
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(5, 5, new CalculatedTaxCollection(), new TaxRuleCollection())
        );
        $cart->setDeliveries(new DeliveryCollection([$delivery]));
        $context->expects(static::once())->method('getRuleIds')->willReturn(['1']);

        $validator = new DeliveryValidator();
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $context);

        static::assertCount(0, $errors);
    }

    public function testValidateWithEmptyRules(): void
    {
        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        $deliveryTime = $this->generateDeliveryTimeDummy();

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('1');
        $shippingMethod->setAvailabilityRuleId(Uuid::randomHex());
        $shippingMethod->setDeliveryTime($deliveryTime);
        $deliveryDate = new DeliveryDate(new \DateTime(), new \DateTime());
        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            $deliveryDate,
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(5, 5, new CalculatedTaxCollection(), new TaxRuleCollection())
        );
        $cart->setDeliveries(new DeliveryCollection([$delivery]));
        $context->expects(static::once())->method('getRuleIds')->willReturn(['1']);

        $validator = new DeliveryValidator();
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $context);

        static::assertCount(1, $errors);
    }

    public function testValidateWithAvailabilityRules(): void
    {
        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        $deliveryTime = $this->generateDeliveryTimeDummy();

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('1');
        $shippingMethod->setAvailabilityRuleId('1');
        $shippingMethod->setDeliveryTime($deliveryTime);
        $shippingMethod->setActive(true);

        $deliveryDate = new DeliveryDate(new \DateTime(), new \DateTime());
        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            $deliveryDate,
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(5, 5, new CalculatedTaxCollection(), new TaxRuleCollection())
        );
        $cart->setDeliveries(new DeliveryCollection([$delivery]));
        $context->expects(static::once())->method('getRuleIds')->willReturn(['1']);

        $validator = new DeliveryValidator();
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $context);

        static::assertCount(0, $errors);
    }

    public function testValidateWithNotMatchingRules(): void
    {
        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        $deliveryTime = $this->generateDeliveryTimeDummy();

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('1');
        $shippingMethod->setName('Express');
        $shippingMethod->addTranslated('name', 'Express');
        $shippingMethod->setDeliveryTime($deliveryTime);
        $shippingMethod->setAvailabilityRuleId(Uuid::randomHex());
        $shippingMethod->setAvailabilityRuleId('1');
        $deliveryDate = new DeliveryDate(new \DateTime(), new \DateTime());
        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            $deliveryDate,
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(5, 5, new CalculatedTaxCollection(), new TaxRuleCollection())
        );
        $cart->setDeliveries(new DeliveryCollection([$delivery]));
        $context->expects(static::once())->method('getRuleIds')->willReturn(['2']);

        $validator = new DeliveryValidator();
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $context);

        static::assertCount(1, $errors);
        static::assertInstanceOf(ShippingMethodBlockedError::class, $errors->first());
        static::assertSame('shipping-method-blocked-Express', $errors->first()->getId());
    }

    public function testValidateWithMultiDeliveries(): void
    {
        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        $deliveryTime = $this->generateDeliveryTimeDummy();

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('1');
        $shippingMethod->setName('Express');
        $shippingMethod->addTranslated('name', 'Express');
        $shippingMethod->setDeliveryTime($deliveryTime);
        $shippingMethod->setAvailabilityRuleId(Uuid::randomHex());
        $deliveryDate = new DeliveryDate(new \DateTime(), new \DateTime());
        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            $deliveryDate,
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(5, 5, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $delivery2 = new Delivery(
            new DeliveryPositionCollection(),
            $deliveryDate,
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(5, 5, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $cart->setDeliveries(new DeliveryCollection([$delivery, $delivery2]));

        $validator = new DeliveryValidator();
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $context);

        static::assertCount(1, $errors);
        static::assertInstanceOf(ShippingMethodBlockedError::class, $errors->first());
        static::assertSame('shipping-method-blocked-Express', $errors->first()->getId());
    }

    private function generateDeliveryTimeDummy(): DeliveryTimeEntity
    {
        $deliveryTime = new DeliveryTimeEntity();
        $deliveryTime->setMin(1);
        $deliveryTime->setMax(3);
        $deliveryTime->setUnit(DeliveryTimeEntity::DELIVERY_TIME_DAY);

        return $deliveryTime;
    }
}
