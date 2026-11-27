<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Delivery;

use PHPUnit\Framework\Attributes\CoversClass;
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
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DeliveryValidator::class)]
class DeliveryValidatorTest extends TestCase
{
    private const SHIPPING_METHOD_AVAILABILITY_RULE_ID = 'shipping-method-availability-rule-id';

    public function testValidateDeliveryShallBeValid(): void
    {
        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);
        $cart->setDeliveries(new DeliveryCollection([$this->generateDeliveryDummy(self::SHIPPING_METHOD_AVAILABILITY_RULE_ID)]));
        $context->expects($this->once())->method('getRuleIds')->willReturn([self::SHIPPING_METHOD_AVAILABILITY_RULE_ID]);

        $validator = new DeliveryValidator();
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $context);

        static::assertCount(0, $errors, 'A delivery without a valid availability rule id should be valid but an error is thrown.');
    }

    public function testValidateDeliveryShippingMethodWithNoAvailabilityRuleShallBeValid(): void
    {
        $cart = new Cart('test');
        $context = static::createStub(SalesChannelContext::class);
        $cart->setDeliveries(new DeliveryCollection([$this->generateDeliveryDummy(null)]));

        $validator = new DeliveryValidator();
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $context);

        static::assertCount(0, $errors, 'A delivery without an availability rule should be valid but an error is thrown.');
    }

    public function testValidateDeliveryShippingMethodAvailabilityRuleIdWithEmptyStringShallThrowAnError(): void
    {
        $cart = new Cart('test');
        $context = static::createStub(SalesChannelContext::class);
        $cart->setDeliveries(new DeliveryCollection([$this->generateDeliveryDummy('')]));

        $validator = new DeliveryValidator();
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $context);

        static::assertCount(1, $errors, 'A delivery with an empty string as availability rule should not be valid but no error is thrown.');
        static::assertSame('Shipping method Test not available. Reason: rule not matching or inactive', $errors->first()?->getMessage());
    }

    public function testAvailabilityRuleIsCheckedAgainstTheRuleIdsOfEachDelivery(): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getRuleIds')->willReturn([self::SHIPPING_METHOD_AVAILABILITY_RULE_ID]);

        $available = $this->generateDeliveryDummy(self::SHIPPING_METHOD_AVAILABILITY_RULE_ID);
        $available->setRuleIds([self::SHIPPING_METHOD_AVAILABILITY_RULE_ID]);
        $blocked = $this->generateDeliveryDummy(self::SHIPPING_METHOD_AVAILABILITY_RULE_ID);
        $blocked->setRuleIds([]);

        $cart = new Cart('test');
        $cart->setDeliveries(new DeliveryCollection([$available, $blocked]));

        $errors = new ErrorCollection();
        (new DeliveryValidator())->validate($cart, $errors, $context);

        static::assertCount(1, $errors, 'Only the delivery whose own rule ids miss the availability rule should be blocked.');
        static::assertSame('Shipping method Test not available. Reason: rule not matching or inactive', $errors->first()?->getMessage());
    }

    public function testDeliveryWithoutMatchedRuleIdsIsCheckedAgainstTheContextRuleIds(): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getRuleIds')->willReturn([self::SHIPPING_METHOD_AVAILABILITY_RULE_ID]);

        $cart = new Cart('test');
        $cart->setDeliveries(new DeliveryCollection([$this->generateDeliveryDummy(self::SHIPPING_METHOD_AVAILABILITY_RULE_ID)]));

        $errors = new ErrorCollection();
        (new DeliveryValidator())->validate($cart, $errors, $context);

        static::assertCount(0, $errors);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testAvailabilityRuleIsCheckedAgainstTheContextRuleIdsWithoutTheMajorFlag(): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getRuleIds')->willReturn([self::SHIPPING_METHOD_AVAILABILITY_RULE_ID]);

        $matchedByDelivery = $this->generateDeliveryDummy(self::SHIPPING_METHOD_AVAILABILITY_RULE_ID);
        $matchedByDelivery->setRuleIds([self::SHIPPING_METHOD_AVAILABILITY_RULE_ID]);
        $notMatchedByDelivery = $this->generateDeliveryDummy(self::SHIPPING_METHOD_AVAILABILITY_RULE_ID);
        $notMatchedByDelivery->setRuleIds([]);

        $cart = new Cart('test');
        $cart->setDeliveries(new DeliveryCollection([$matchedByDelivery, $notMatchedByDelivery]));

        $errors = new ErrorCollection();
        (new DeliveryValidator())->validate($cart, $errors, $context);

        static::assertCount(0, $errors, 'Without the major flag the context rule ids decide for every delivery.');
    }

    private function generateDeliveryDummy(?string $availabilityRuleId): Delivery
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('shipping-method-id');
        $shippingMethod->setTranslated(['name' => 'Test']);
        $shippingMethod->setAvailabilityRuleId($availabilityRuleId);
        $shippingMethod->setActive(true);

        $deliveryDate = new DeliveryDate(new \DateTime(), new \DateTime());

        return new Delivery(
            new DeliveryPositionCollection(),
            $deliveryDate,
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(5, 5, new CalculatedTaxCollection(), new TaxRuleCollection())
        );
    }
}
