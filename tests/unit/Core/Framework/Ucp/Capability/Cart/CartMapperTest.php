<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Capability\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Ucp\Capability\Attribution\AttributionExtractor;
use Shopware\Core\Framework\Ucp\Capability\CapabilityIntersection;
use Shopware\Core\Framework\Ucp\Capability\Cart\CartMapper;
use Shopware\Core\Framework\Ucp\Capability\Discount\DiscountMapper;
use Shopware\Core\Framework\Ucp\Capability\Loyalty\LoyaltyAggregator;
use Shopware\Core\Framework\Ucp\Capability\Signals\SignalsExtractor;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSalesChannelConfigEntity;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Pins the UCP cart wire-format. The mapper is the only translator between
 * Shopware Cart and the platform-facing cart response, so the keys, the
 * minor-units conversion, the discount/loyalty/signals gating, and the
 * promotion-item filtering must all stay stable across spec versions.
 *
 * @internal
 */
#[CoversClass(CartMapper::class)]
class CartMapperTest extends TestCase
{
    public function testEmitsBasicCartShapeWithoutUcpContext(): void
    {
        $cart = new Cart('cart-1');
        $cart->setLineItems(new LineItemCollection([
            $this->productLine('li-1', 'Widget', productNumber: 'SKU-1', unit: 19.99, qty: 2),
        ]));
        $cart->setPrice(new CartPrice(
            39.98,
            39.98,
            33.60,
            new CalculatedTaxCollection([new CalculatedTax(6.38, 19, 39.98)]),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_GROSS,
            39.98
        ));

        $payload = $this->mapper()->toResponse($cart, $this->salesChannelContext('EUR'));

        static::assertSame('cart-1', $payload['id']);
        static::assertSame('EUR', $payload['currency']);
        static::assertCount(1, $payload['line_items']);
        static::assertSame('SKU-1', $payload['line_items'][0]['item']['id']);
        static::assertSame(1999, $payload['line_items'][0]['item']['price']);
        static::assertSame(3998, $payload['line_items'][0]['line_total']['amount']);
        static::assertSame(2, $payload['line_items'][0]['quantity']);

        // Totals split per UCP overview.md
        $types = array_column($payload['totals'], 'type');
        static::assertContains('subtotal', $types);
        static::assertContains('tax', $types);
        static::assertContains('total', $types);

        // Without a UcpRequestContext we never emit signals / attribution / loyalty.
        static::assertArrayNotHasKey('signals', $payload);
        static::assertArrayNotHasKey('attribution', $payload);
        static::assertArrayNotHasKey('loyalty', $payload);
    }

    public function testFiltersOutPromotionLineItemsFromLineItemsArray(): void
    {
        $cart = new Cart('cart-1');
        $cart->setLineItems(new LineItemCollection([
            $this->productLine('li-1', 'Widget', productNumber: 'SKU-1', unit: 10.0, qty: 1),
            $this->promotionLine('promo-1', '5€ off', -5.0),
        ]));
        $cart->setPrice($this->cartPrice(5.0, 5.0));

        $payload = $this->mapper()->toResponse($cart, $this->salesChannelContext('EUR'));

        // Only the real product item appears in line_items[].
        static::assertCount(1, $payload['line_items']);
        static::assertSame('li-1', $payload['line_items'][0]['id']);

        // The promotion folds into the totals[] as a `discount` line.
        $types = array_column($payload['totals'], 'type');
        static::assertContains('discount', $types);
    }

    public function testEmitsContinueUrlWhenProvided(): void
    {
        $cart = new Cart('cart-1');
        $cart->setLineItems(new LineItemCollection([]));
        $cart->setPrice($this->cartPrice());

        $payload = $this->mapper()->toResponse(
            $cart,
            $this->salesChannelContext('EUR'),
            continueUrl: 'https://shop.example/checkout/confirm?ucp_token=abc'
        );

        static::assertSame('https://shop.example/checkout/confirm?ucp_token=abc', $payload['continue_url']);
    }

    public function testGatesSignalsBehindSignatureVerification(): void
    {
        $signals = $this->createMock(SignalsExtractor::class);
        $signals->method('extract')->willReturnCallback(static function ($_, $__, bool $verified): array {
            return $verified ? ['dev.ucp.buyer_ip' => '198.51.100.7'] : [];
        });

        $mapper = $this->mapper(signals: $signals);
        $cart = new Cart('c1');
        $cart->setLineItems(new LineItemCollection([]));
        $cart->setPrice($this->cartPrice());

        $platformRequest = ['signals' => ['dev.ucp.buyer_ip' => '198.51.100.7']];

        // signatureVerified=false → signals dropped per overview.md §"Signals"
        $unsigned = $mapper->toResponse(
            $cart,
            $this->salesChannelContext('EUR'),
            ucpContext: $this->ucpContext(['dev.ucp.shopping.cart']),
            platformRequest: $platformRequest,
            signatureVerified: false,
        );
        static::assertArrayNotHasKey('signals', $unsigned);

        // signatureVerified=true → signals echoed back
        $signed = $mapper->toResponse(
            $cart,
            $this->salesChannelContext('EUR'),
            ucpContext: $this->ucpContext(['dev.ucp.shopping.cart']),
            platformRequest: $platformRequest,
            signatureVerified: true,
        );
        static::assertArrayHasKey('signals', $signed);
        static::assertSame('198.51.100.7', $signed['signals']['dev.ucp.buyer_ip']);
    }

    public function testEmitsDiscountsBlockOnlyWhenCapabilityNegotiated(): void
    {
        $discountMapper = $this->createMock(DiscountMapper::class);
        $discountMapper->expects($this->once())->method('extract')->willReturn(['applied' => [['code' => 'X']]]);
        $discountMapper->expects($this->once())->method('extractRejectedCodes')->willReturn([]);

        $cart = new Cart('c1');
        $cart->setLineItems(new LineItemCollection([]));
        $cart->setPrice($this->cartPrice());

        $payload = $this->mapper(discount: $discountMapper)->toResponse(
            $cart,
            $this->salesChannelContext('EUR'),
            ucpContext: $this->ucpContext(['dev.ucp.shopping.discount']),
        );

        static::assertSame(['applied' => [['code' => 'X']]], $payload['discounts']);
    }

    public function testOmitsDiscountsBlockWhenCapabilityNotNegotiated(): void
    {
        $discountMapper = $this->createMock(DiscountMapper::class);
        $discountMapper->expects($this->never())->method('extract');

        $cart = new Cart('c1');
        $cart->setLineItems(new LineItemCollection([]));
        $cart->setPrice($this->cartPrice());

        $payload = $this->mapper(discount: $discountMapper)->toResponse(
            $cart,
            $this->salesChannelContext('EUR'),
            ucpContext: $this->ucpContext(['dev.ucp.shopping.cart']),
        );

        static::assertArrayNotHasKey('discounts', $payload);
    }

    public function testEmitsLoyaltyBlockOnlyWhenCapabilityNegotiated(): void
    {
        $loyalty = $this->createMock(LoyaltyAggregator::class);
        $loyalty->expects($this->once())->method('aggregate')->willReturn([['namespace' => 'com.acme', 'balance' => 100]]);

        $cart = new Cart('c1');
        $cart->setLineItems(new LineItemCollection([]));
        $cart->setPrice($this->cartPrice());

        $payload = $this->mapper(loyalty: $loyalty)->toResponse(
            $cart,
            $this->salesChannelContext('EUR'),
            ucpContext: $this->ucpContext(['dev.ucp.shopping.loyalty']),
        );

        static::assertCount(1, $payload['loyalty']);
        static::assertSame('com.acme', $payload['loyalty'][0]['namespace']);
    }

    public function testIncludesAddressRegionAndPostalCodeFromShippingLocation(): void
    {
        $cart = new Cart('c1');
        $cart->setLineItems(new LineItemCollection([]));
        $cart->setPrice($this->cartPrice());

        $payload = $this->mapper()->toResponse(
            $cart,
            $this->salesChannelContextWithAddress('EUR', 'DE', regionCode: 'BE', zip: '10115'),
        );

        static::assertSame('DE', $payload['context']['address_country']);
        static::assertSame('BE', $payload['context']['address_region']);
        static::assertSame('10115', $payload['context']['postal_code']);
    }

    public function testEmitsExpiresAtAsIso8601(): void
    {
        $cart = new Cart('c1');
        $cart->setLineItems(new LineItemCollection([]));
        $cart->setPrice($this->cartPrice());

        $payload = $this->mapper()->toResponse($cart, $this->salesChannelContext('EUR'));

        static::assertIsString($payload['expires_at']);
        // RFC 3339 / ISO 8601 — gmdate('c') always emits this shape.
        static::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/',
            $payload['expires_at']
        );
    }

    private function mapper(
        ?DiscountMapper $discount = null,
        ?LoyaltyAggregator $loyalty = null,
        ?SignalsExtractor $signals = null,
    ): CartMapper {
        $loyaltyDefault = $loyalty ?? new LoyaltyAggregator([]);
        $signalsDefault = $signals ?? new SignalsExtractor();
        $discountDefault = $discount ?? $this->stubDiscountMapper();

        return new CartMapper(
            $discountDefault,
            $loyaltyDefault,
            $signalsDefault,
            new AttributionExtractor(),
        );
    }

    private function stubDiscountMapper(): DiscountMapper
    {
        $m = $this->createMock(DiscountMapper::class);
        $m->method('extract')->willReturn([]);
        $m->method('extractRejectedCodes')->willReturn([]);

        return $m;
    }

    /**
     * @param list<string> $capabilities
     */
    private function ucpContext(array $capabilities): UcpRequestContext
    {
        $intersection = new CapabilityIntersection(
            array_fill_keys($capabilities, [['version' => '2026-01-23']]),
            '2026-01-23',
        );

        $config = new UcpSalesChannelConfigEntity();
        $config->setSalesChannelId('00000000000000000000000000000000');
        $config->setUcpVersion('2026-01-23');

        return new UcpRequestContext(
            $config,
            $this->salesChannelContext('EUR'),
            $intersection,
            'https://platform.example/profile'
        );
    }

    private function productLine(string $id, string $label, string $productNumber, float $unit, int $qty): LineItem
    {
        $item = new LineItem($id, 'product', null, $qty);
        $item->setStackable(true);
        $item->setLabel($label);
        $item->setReferencedId($id);
        $item->setPayload(['productNumber' => $productNumber]);
        $item->setPrice(new CalculatedPrice($unit, $unit * $qty, new CalculatedTaxCollection(), new TaxRuleCollection()));

        return $item;
    }

    private function promotionLine(string $id, string $label, float $price): LineItem
    {
        $item = new LineItem($id, 'promotion', null, 1);
        $item->setLabel($label);
        $item->setPrice(new CalculatedPrice($price, $price, new CalculatedTaxCollection(), new TaxRuleCollection()));

        return $item;
    }

    private function cartPrice(float $net = 0.0, float $gross = 0.0): CartPrice
    {
        return new CartPrice(
            $net,
            $gross,
            $net,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_GROSS,
            $gross
        );
    }

    private function salesChannelContext(string $currencyIso): SalesChannelContext
    {
        return $this->salesChannelContextWithAddress($currencyIso, 'DE');
    }

    private function salesChannelContextWithAddress(
        string $currencyIso,
        string $countryIso,
        ?string $regionCode = null,
        ?string $zip = null,
    ): SalesChannelContext {
        $currency = new CurrencyEntity();
        $currency->setId('cur-' . $currencyIso);
        $currency->setIsoCode($currencyIso);

        $country = new CountryEntity();
        $country->setId('country-' . $countryIso);
        $country->setIso($countryIso);

        $address = null;
        if ($regionCode !== null || $zip !== null) {
            $address = new CustomerAddressEntity();
            $address->setId('addr-1');
            $address->setCountry($country);
            if ($zip !== null) {
                $address->setZipcode($zip);
            }
            if ($regionCode !== null) {
                $state = new CountryStateEntity();
                $state->setId('state-1');
                $state->setShortCode($regionCode);
                $address->setCountryState($state);
            }
        }

        $shippingLocation = $address !== null
            ? ShippingLocation::createFromAddress($address)
            : new ShippingLocation($country, null, null);

        $ctx = $this->createMock(SalesChannelContext::class);
        $ctx->method('getCurrency')->willReturn($currency);
        $ctx->method('getShippingLocation')->willReturn($shippingLocation);
        $ctx->method('getLanguageId')->willReturn('en-language-id');

        return $ctx;
    }
}
