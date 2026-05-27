<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Capability\Discount;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Ucp\Capability\Discount\DiscountMapper;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Verifies the wire-format of UCP `discounts.applied[]` and the
 * promotion-error → UCP spec-code mapping. The mapper is the single source of
 * truth for what platforms see for promotions; the spec-code mapping is part
 * of the public contract defined in `discount.md §"Error Codes"`.
 *
 * @internal
 */
#[CoversClass(DiscountMapper::class)]
class DiscountMapperTest extends TestCase
{
    public function testReturnsEmptyArrayWhenCartHasNoPromotions(): void
    {
        $cart = new Cart('cart-1');
        $cart->setLineItems(new LineItemCollection([
            $this->plainLineItem('item-1', 'Plain', 9.99),
        ]));

        $result = (new DiscountMapper())->extract($cart, $this->context());

        static::assertSame([], $result);
    }

    public function testExtractsExplicitPromotionCode(): void
    {
        $cart = new Cart('cart-1');
        $cart->setLineItems(new LineItemCollection([
            $this->plainLineItem('item-1', 'Plain', 19.99),
            $this->promotionLineItem('promo-1', '10% OFF', -2.0, code: 'WELCOME10'),
        ]));

        $result = (new DiscountMapper())->extract($cart, $this->context());

        static::assertArrayHasKey('applied', $result);
        static::assertCount(1, $result['applied']);
        $entry = $result['applied'][0];
        static::assertSame('10% OFF', $entry['title']);
        static::assertSame('WELCOME10', $entry['code']);
        // abs() ensures positive minor-units amount regardless of sign convention.
        static::assertSame(200, $entry['amount']);
        static::assertFalse($entry['automatic']);
    }

    public function testAutomaticPromotionHasNoCodeAndIsFlaggedAutomatic(): void
    {
        $cart = new Cart('cart-1');
        $cart->setLineItems(new LineItemCollection([
            $this->promotionLineItem('promo-auto', 'Auto -5€', -5.0, code: null),
        ]));

        $result = (new DiscountMapper())->extract($cart, $this->context());

        static::assertArrayHasKey('applied', $result);
        static::assertTrue($result['applied'][0]['automatic']);
        static::assertArrayNotHasKey('code', $result['applied'][0]);
    }

    public function testEmitsAllocationsWhenCompositionIsPresent(): void
    {
        $promotion = $this->promotionLineItem('promo-1', 'Bundle', -10.0, code: 'BUNDLE');
        $promotion->setPayload([
            'code' => 'BUNDLE',
            'composition' => [
                ['id' => 'line-a', 'amount' => 600],
                ['id' => 'line-b', 'amount' => 400],
            ],
        ]);

        $cart = new Cart('cart-1');
        $cart->setLineItems(new LineItemCollection([$promotion]));

        $result = (new DiscountMapper())->extract($cart, $this->context());

        static::assertArrayHasKey('applied', $result);
        static::assertCount(2, $result['applied'][0]['allocations']);
        static::assertSame('$.line_items[?(@.id=="line-a")]', $result['applied'][0]['allocations'][0]['path']);
        // Allocations always emit negative amounts when the discount is positive.
        static::assertSame(-1000, $result['applied'][0]['allocations'][0]['amount']);
    }

    public function testIgnoresNonPromotionErrors(): void
    {
        $cart = new Cart('cart-1');
        $cart->addErrors(new FakeError('shipping-method-blocked', 'Shipping blocked', persistent: true));

        static::assertSame([], (new DiscountMapper())->extractRejectedCodes($cart));
    }

    public function testMapsPromotionErrorKeyToSpecCodeNotFound(): void
    {
        $cart = $this->cartWithError('promotion-not-found:WELCOME99');
        $msgs = (new DiscountMapper())->extractRejectedCodes($cart);
        static::assertSame('discount_code_invalid', $msgs[0]['code']);
        static::assertSame('$.discounts.codes', $msgs[0]['path']);
    }

    public function testMapsPromotionErrorKeyToSpecCodeExpired(): void
    {
        $cart = $this->cartWithError('promotion-expired:OLD2024');
        static::assertSame('discount_code_expired', (new DiscountMapper())->extractRejectedCodes($cart)[0]['code']);
    }

    public function testMapsPromotionErrorKeyToSpecCodeAlreadyApplied(): void
    {
        $cart = $this->cartWithError('promotion-already-placed-in-cart:WELCOME10');
        static::assertSame('discount_code_already_applied', (new DiscountMapper())->extractRejectedCodes($cart)[0]['code']);
    }

    public function testMapsPromotionErrorKeyToSpecCodeNotEligible(): void
    {
        $cart = $this->cartWithError('promotion-not-eligible:VIP');
        static::assertSame('discount_code_not_applicable', (new DiscountMapper())->extractRejectedCodes($cart)[0]['code']);
    }

    public function testMapsPromotionErrorKeyToSpecCodeUnknownPromotionFallback(): void
    {
        $cart = $this->cartWithError('promotion-some-new-edge-case');
        static::assertSame('discount_code_rejected', (new DiscountMapper())->extractRejectedCodes($cart)[0]['code']);
    }

    private function cartWithError(string $key): Cart
    {
        $cart = new Cart('cart-1');
        $cart->addErrors(new FakeError($key, 'message'));

        return $cart;
    }

    private function plainLineItem(string $id, string $label, float $price): LineItem
    {
        $item = new LineItem($id, 'product');
        $item->setLabel($label);
        $item->setPrice(new CalculatedPrice($price, $price, new CalculatedTaxCollection(), new TaxRuleCollection()));

        return $item;
    }

    private function promotionLineItem(string $id, string $label, float $price, ?string $code): LineItem
    {
        $item = new LineItem($id, 'promotion');
        $item->setLabel($label);
        $item->setPrice(new CalculatedPrice($price, $price, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $item->setPayload(['code' => $code]);

        return $item;
    }

    private function context(): SalesChannelContext
    {
        return $this->createMock(SalesChannelContext::class);
    }
}

/**
 * @internal
 */
final class FakeError extends Error
{
    public function __construct(
        private readonly string $messageKey,
        string $message,
        private readonly bool $persistent = false,
    ) {
        parent::__construct($message);
    }

    public function getMessageKey(): string
    {
        return $this->messageKey;
    }

    public function getId(): string
    {
        return $this->messageKey;
    }

    public function isPersistent(): bool
    {
        return $this->persistent;
    }

    public function blockOrder(): bool
    {
        return false;
    }

    public function getLevel(): int
    {
        return $this->persistent ? 20 : 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return [];
    }
}
