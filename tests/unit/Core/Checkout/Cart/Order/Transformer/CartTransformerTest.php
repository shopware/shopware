<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order\Transformer;

use PHPUnit\Framework\Attributes\CoversClass;
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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(CartTransformer::class)]
#[Package('checkout')]
class CartTransformerTest extends TestCase
{
    public function testCartTransformation(): void
    {
        $stateId = Uuid::randomHex();
        $cart = $this->createCart();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cartTransformer = CartTransformer::transform($cart, $salesChannelContext, $stateId);

        static::assertIsString($cartTransformer['deepLinkCode']);
        static::assertIsString($cartTransformer['orderDateTime']);
        unset($cartTransformer['deepLinkCode']);
        unset($cartTransformer['orderDateTime']);

        static::assertEquals($this->getExpectedBaseData($stateId, $salesChannelContext), $cartTransformer);
    }

    public function testCartTransformationWithCreatedByUserId(): void
    {
        $adminUserId = '123467890';
        $stateId = Uuid::randomHex();
        $cart = $this->createCart();
        $context = Context::createDefaultContext(new AdminSalesChannelApiSource(Uuid::randomHex(), Context::createDefaultContext(new AdminApiSource($adminUserId))));
        $salesChannelContext = Generator::generateSalesChannelContext($context);

        $cartTransformer = CartTransformer::transform($cart, $salesChannelContext, $stateId, false);

        static::assertArrayHasKey('createdById', $cartTransformer);
        static::assertSame($adminUserId, $cartTransformer['createdById']);
        unset($cartTransformer['createdById']);

        static::assertEquals($this->getExpectedBaseData($stateId, $salesChannelContext), $cartTransformer);
    }

    public function testCartTransformationWithoutOrderData(): void
    {
        $stateId = Uuid::randomHex();
        $cart = $this->createCart();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cartTransformer = CartTransformer::transform($cart, $salesChannelContext, $stateId, false);

        static::assertArrayNotHasKey('deepLinkCode', $cartTransformer);
        static::assertArrayNotHasKey('orderDateTime', $cartTransformer);

        static::assertEquals($this->getExpectedBaseData($stateId, $salesChannelContext), $cartTransformer);
    }

    /**
     * @return array<string, mixed>
     */
    public function getExpectedBaseData(string $stateId, SalesChannelContext $salesChannelContext): array
    {
        return [
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
            'currencyId' => $salesChannelContext->getCurrencyId(),
            'currencyFactor' => $salesChannelContext->getCurrency()->getFactor(),
            'salesChannelId' => $salesChannelContext->getSalesChannelId(),
            'lineItems' => [],
            'deliveries' => [],
            'customerComment' => 'customerCommentTest',
            'affiliateCode' => 'AffiliateCodeTest',
            'campaignCode' => 'campaignCodeTest',
            'source' => 'sourceTest',
            'itemRounding' => json_decode(Json::encode($salesChannelContext->getItemRounding()), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(Json::encode($salesChannelContext->getTotalRounding()), true, 512, \JSON_THROW_ON_ERROR),
        ];
    }

    private function createCart(): Cart
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
}
