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
use Shopware\Core\Checkout\Cart\Order\Transformer\CartTransformer;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
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
#[CoversClass(CartTransformer::class)]
class CartTransformerTest extends TestCase
{
    public function testCartTransformation(): void
    {
        $adminUserId = '123467890';
        $stateId = Uuid::randomHex();

        $cart = $this->createCart();
        $salesChannelContextMock = $this->createSalesChannelMock($adminUserId);

        $cartTransformer = CartTransformer::transform($cart, $salesChannelContextMock, $stateId);

        $currency = $salesChannelContextMock->getCurrency();

        $expected = [
            'price' => new CartPrice(
                100.0,
                100.0,
                100.0,
                new CalculatedTaxCollection([new CalculatedTax(0.0, 38.0, 100.0),
                ]),
                new TaxRuleCollection(),
                CartPrice::TAX_STATE_GROSS
            ),
            'shippingCosts' => new CalculatedPrice(5, 5, new CalculatedTaxCollection(), new TaxRuleCollection(), 1),
            'stateId' => $stateId,
            'currencyId' => $currency->getId(),
            'currencyFactor' => $currency->getFactor(),
            'salesChannelId' => $salesChannelContextMock->getSalesChannel()->getId(),
            'lineItems' => [],
            'deliveries' => [],
            'customerComment' => 'customerCommentTest',
            'affiliateCode' => 'AffiliateCodeTest',
            'campaignCode' => 'campaignCodeTest',
            'source' => 'sourceTest',
            'createdById' => $adminUserId,
            'itemRounding' => [],
            'totalRounding' => [],
        ];

        static::assertIsString($cartTransformer['deepLinkCode']);
        static::assertIsString($cartTransformer['orderDateTime']);

        unset($cartTransformer['deepLinkCode']);
        unset($cartTransformer['orderDateTime']);

        static::assertEquals($expected, $cartTransformer);
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
