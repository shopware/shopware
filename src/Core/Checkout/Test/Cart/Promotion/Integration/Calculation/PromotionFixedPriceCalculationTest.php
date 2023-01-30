<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Integration\Calculation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionIntegrationTestBehaviour;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionTestFixtureBehaviour;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class PromotionFixedPriceCalculationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;
    use PromotionIntegrationTestBehaviour;

    protected EntityRepository $productRepository;

    protected CartService $cartService;

    protected EntityRepository $promotionRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->promotionRepository = $this->getContainer()->get('promotion.repository');
        $this->cartService = $this->getContainer()->get(CartService::class);
    }

    /**
     * This test verifies that our promotion components are really involved in our checkout.
     * We test that we always end with a final price of our promotion if that one is set to a "fixed price".
     * Our price would be 40 EUR. It must not matter how many items and products we have in there,
     * the final price should always be 40 EUR.
     *
     * @group promotions
     */
    public function testFixedUnitDiscount(): void
    {
        $productId = Uuid::randomHex();
        $productIdTwo = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF19';

        // add a new sample products
        $this->createTestFixtureProduct($productId, 100, 19, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($productIdTwo, 100, 7, $this->getContainer(), $this->context);

        // add a new promotion
        $this->createTestFixtureFixedDiscountPromotion($promotionId, 40, PromotionDiscountEntity::SCOPE_CART, $code, $this->getContainer(), $this->context);

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // add products to cart
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($productIdTwo, 1, $cart, $this->cartService, $this->context);

        // add promotion to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        static::assertEquals(40, $cart->getPrice()->getPositionPrice());
        static::assertEquals(40, $cart->getPrice()->getTotalPrice());
        static::assertEquals(35.49, $cart->getPrice()->getNetPrice());
    }

    /**
     * if a automatic fixed price promotion (no code necessary) discount is removed
     * it should not be added again. This is a new feature - to block automatic promotions.
     *
     * @group promotions
     */
    public function testRemoveOfFixedUnitPromotionsWithoutCode(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        // add a new sample product
        $this->createTestFixtureProduct($productId, 100, 19, $this->getContainer(), $context);

        // add a new promotion
        $this->createTestFixtureFixedDiscountPromotion($promotionId, 40, PromotionDiscountEntity::SCOPE_CART, null, $this->getContainer(), $context);

        $cart = $this->cartService->getCart($context->getToken(), $context);

        // create first product and add to cart
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $context);

        static::assertCount(2, $cart->getLineItems(), 'We expect two lineItems in cart');

        static::assertEquals(40, $cart->getPrice()->getPositionPrice());
        static::assertEquals(40, $cart->getPrice()->getTotalPrice());
        static::assertEquals(33.61, $cart->getPrice()->getNetPrice(), 'Discounted cart does not have expected net price');

        $discountLineItem = $cart->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE)->first();
        static::assertNotNull($discountLineItem);
        $discountId = $discountLineItem->getId();

        // and try to remove promotion
        $cart = $this->cartService->remove($cart, $discountId, $context);

        static::assertCount(1, $cart->getLineItems(), 'We expect 1 lineItem in cart');

        static::assertEquals(100, $cart->getPrice()->getPositionPrice());
        static::assertEquals(100, $cart->getPrice()->getTotalPrice());
        static::assertEquals(84.03, $cart->getPrice()->getNetPrice(), 'Even after promotion delete try it should be present and product should be discounted');
    }

    /**
     * This test verifies that we use the max value of our currency
     * instead of the defined fixed price, if existing.
     * Thus we create a promotion with a fixed price of 80
     * That would lead to 80 EUR for the product
     * But for your currency defined price, we use 65 as fixed price instead.
     * Our test needs to verify that we use 30 EUR, and end with a product sum of 65 EUR in the end.
     *
     * @group promotions
     */
    public function testFixedUnitPriceDiscountWithCurrencyPrices(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $productGross = 100;
        $fixedPriceValue = 80;
        $currencyMaxValue = 65.0;

        $expectedPrice = $currencyMaxValue;

        // add a new sample product
        $this->createTestFixtureProduct($productId, $productGross, 19, $this->getContainer(), $context);

        $discountId = $this->createTestFixtureFixedDiscountPromotion($promotionId, $fixedPriceValue, PromotionDiscountEntity::SCOPE_CART, $code, $this->getContainer(), $context);

        $this->createTestFixtureAdvancedPrice($discountId, Defaults::CURRENCY, $currencyMaxValue, $this->getContainer());

        $cart = $this->cartService->getCart($context->getToken(), $context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $context);

        static::assertEquals($productGross, $cart->getPrice()->getTotalPrice());

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $context);

        static::assertEquals($expectedPrice, $cart->getPrice()->getPositionPrice());
        static::assertEquals($expectedPrice, $cart->getPrice()->getTotalPrice());
        static::assertEquals(54.62, $cart->getPrice()->getNetPrice());
    }

    /**
     * This test verifies that we can assign a fixed total price for our promotion discount.
     * We test this by adding a scope of the "entire cart" which consists of 2 products with different quantities.
     * Then we add a promotion with a fixed price of 100 EUR.
     * This means that our final cart price should be 100 EUR and the discount need to be calculated correctly
     * by considering the existing cart items.
     *
     * @group promotions
     */
    public function testFixedCartPriceDiscount(): void
    {
        $productId1 = Uuid::randomHex();
        $productId2 = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF19';

        // add 2 test products
        $this->createTestFixtureProduct($productId1, 200, 19, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($productId2, 50, 19, $this->getContainer(), $this->context);

        // add a new promotion
        $this->createTestFixtureFixedDiscountPromotion($promotionId, 100, PromotionDiscountEntity::SCOPE_CART, $code, $this->getContainer(), $this->context);

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // create first product and add to cart
        $cart = $this->addProduct($productId1, 2, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($productId2, 3, $cart, $this->cartService, $this->context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        static::assertCount(3, $cart->getLineItems(), 'We expect 2 products and 1 discount in cart');

        static::assertEquals(100, $cart->getPrice()->getPositionPrice());
        static::assertEquals(100, $cart->getPrice()->getTotalPrice());
        static::assertEquals(84.03, $cart->getPrice()->getNetPrice());
    }
}
