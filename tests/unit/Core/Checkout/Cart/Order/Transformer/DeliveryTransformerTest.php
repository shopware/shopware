<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order\Transformer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Order\IdStruct;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Order\Transformer\DeliveryTransformer;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\AdminSalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[CoversClass(DeliveryTransformer::class)]
class DeliveryTransformerTest extends TestCase
{
    public function testTransformCollection(): void
    {
        $cart = $this->createCart();
        $delivery = $cart->getDeliveries()->first();
        $lineItems = [];
        $addresses = [];
        $stateId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $result = DeliveryTransformer::transformCollection(
            $cart->getDeliveries(),
            $lineItems,
            $stateId,
            $context,
            $addresses
        );

        static::assertCount(1, $result);
        static::assertInstanceOf(Delivery::class, $delivery);
        static::assertEquals(
            DeliveryTransformer::transform($delivery, $lineItems, $stateId, $context, $addresses),
            $result[0]
        );

        static::assertEquals(
            [
                'shippingMethodId' => $delivery->getShippingMethod()->getId(),
                'shippingCosts' => $delivery->getShippingCosts(),
                'positions' => [],
                'stateId' => $stateId,
                'shippingDateEarliest' => $delivery->getDeliveryDate()->getEarliest()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'shippingDateLatest' => $delivery->getDeliveryDate()->getLatest()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            $result[0]
        );
    }

    public function testTransformCollectionWithDeliveryExisted(): void
    {
        $cart = $this->createCart();
        $delivery = $cart->getDeliveries()->first();
        static::assertInstanceOf(Delivery::class, $delivery);
        $delivery->addExtension(OrderConverter::ORIGINAL_ID, new IdStruct('deliveryId'));
        $lineItems = [];
        $addresses = [];
        $stateId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $result = DeliveryTransformer::transformCollection(
            $cart->getDeliveries(),
            $lineItems,
            $stateId,
            $context,
            $addresses
        );

        static::assertCount(1, $result);
        static::assertEquals(
            DeliveryTransformer::transform($delivery, $lineItems, $stateId, $context, $addresses),
            $result[0]
        );

        static::assertEquals(
            [
                'id' => 'deliveryId',
                'shippingMethodId' => $delivery->getShippingMethod()->getId(),
                'shippingCosts' => $delivery->getShippingCosts(),
                'positions' => [],
                'stateId' => $stateId,
                'shippingDateEarliest' => $delivery->getDeliveryDate()->getEarliest()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'shippingDateLatest' => $delivery->getDeliveryDate()->getLatest()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            $result[0]
        );
    }

    public function testTransformCollectionWithOriginalAddresses(): void
    {
        $cart = $this->createCart();
        $delivery = $cart->getDeliveries()->first();
        static::assertInstanceOf(Delivery::class, $delivery);
        $delivery->addExtension(OrderConverter::ORIGINAL_ADDRESS_ID, new IdStruct('originalAddressId'));
        $delivery->addExtension(OrderConverter::ORIGINAL_ADDRESS_VERSION_ID, new IdStruct('originalAddressVersionId'));
        $lineItems = [];
        $addresses = [];
        $stateId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $result = DeliveryTransformer::transformCollection(
            $cart->getDeliveries(),
            $lineItems,
            $stateId,
            $context,
            $addresses
        );

        static::assertCount(1, $result);
        static::assertEquals(
            DeliveryTransformer::transform($delivery, $lineItems, $stateId, $context, $addresses),
            $result[0]
        );

        static::assertEquals(
            [
                'shippingMethodId' => $delivery->getShippingMethod()->getId(),
                'shippingCosts' => $delivery->getShippingCosts(),
                'positions' => [],
                'stateId' => $stateId,
                'shippingOrderAddressId' => 'originalAddressId',
                'shippingOrderAddressVersionId' => 'originalAddressVersionId',
                'shippingDateEarliest' => $delivery->getDeliveryDate()->getEarliest()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'shippingDateLatest' => $delivery->getDeliveryDate()->getLatest()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            $result[0]
        );
    }

    public function createCart(): Cart
    {
        $cart = new Cart('test');
        $cart->setPrice(
            new CartPrice(
                100,
                100,
                100,
                new CalculatedTaxCollection([
                    new CalculatedTax(0, 38, 100),
                ]),
                new TaxRuleCollection(),
                CartPrice::TAX_STATE_GROSS,
                100
            )
        );
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('1');
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
        $cart->setCustomerComment('customerCommentTest');
        $cart->setAffiliateCode('AffiliateCodeTest');
        $cart->setCampaignCode('campaignCodeTest');
        $cart->setSource('sourceTest');

        return $cart;
    }

    public function createSalesChannelMock(string $adminUserId): SalesChannelContext&MockObject
    {
        $salesChannelContextMock = $this->createMock(SalesChannelContext::class);
        $contextSourceMock = $this->createMock(AdminSalesChannelApiSource::class);
        $sourceTest = $this->createMock(AdminApiSource::class);

        $contextMockAdminSales = new Context($contextSourceMock);
        $contextMockAdminApi = new Context($sourceTest);

        $contextSourceMock->method('getOriginalContext')->willReturn($contextMockAdminApi);
        $sourceTest->method('getUserId')->willReturn($adminUserId);
        $salesChannelContextMock->method('getContext')->willReturn($contextMockAdminSales);
        $currency = new CurrencyEntity();
        $currency->setId('12345');
        $currency->setFactor(1);

        $salesChannelContextMock->method('getCurrency')->willReturn($currency);
        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setId('123');
        $salesChannelContextMock->method('getSalesChannel')->willReturn($salesChannelEntity);

        return $salesChannelContextMock;
    }
}
