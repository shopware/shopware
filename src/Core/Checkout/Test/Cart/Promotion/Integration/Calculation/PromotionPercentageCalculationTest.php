<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Integration\Calculation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
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
class PromotionPercentageCalculationTest extends TestCase
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

        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->promotionRepository = $this->getContainer()->get('promotion.repository');
        $this->cartService = $this->getContainer()->get(CartService::class);
    }

    /**
     * This test verifies that our promotion components are really involved in our checkout.
     * We add a product to the cart and apply a code for a promotion with 100% discount.
     * Our cart should have a total value of 0,00 in the end.
     *
     * @group promotions
     *
     * @throws CartException
     */
    public function test100PercentageDiscount(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        // add a new sample product
        $this->createTestFixtureProduct($productId, 29, 17, $this->getContainer(), $context);

        // add a new promotion black friday
        $this->createTestFixturePercentagePromotion($promotionId, $code, 100, null, $this->getContainer());

        $cart = $this->cartService->getCart($context->getToken(), $context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $context);

        static::assertEquals(0.0, $cart->getPrice()->getPositionPrice(), 'Position Total Price has to be 0,00');
        static::assertEquals(0.0, $cart->getPrice()->getTotalPrice(), 'Total Price has to be 0,00');
        static::assertEquals(0.0, $cart->getPrice()->getCalculatedTaxes()->getAmount(), 'Taxes have to be 0,00');
        static::assertEquals(0.0, $cart->getPrice()->getNetPrice(), 'Net Price has to be 0,00');
    }

    /**
     * This test verifies that our promotion components are really involved in our checkout.
     * We add a product to the cart and apply a code for a promotion with 50% discount.
     * Our cart should have a total value of 15,00 in the end.
     *
     * @group promotions
     *
     * @throws CartException
     */
    public function test50PercentageDiscount(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        // add a new sample product
        $this->createTestFixtureProduct($productId, 100, 20, $this->getContainer(), $context);

        // add a new promotion black friday
        $this->createTestFixturePercentagePromotion($promotionId, $code, 50, null, $this->getContainer());

        $cart = $this->cartService->getCart($context->getToken(), $context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $context);

        /**
         * Tax rate: 20%
         *
         * 100€ product (gross)
         *          => included taxes: 16.6666666667 => 16.67€
         *          => net price: 83.33€
         *
         * -50% discount promotion
         *      gross: 50€
         *      included taxes: 8.33333333333 => 8.33€
         *      net price: 41.67€
         *
         * Total price:
         *      gross: 50€
         *      included taxes: 8.34
         *      net price: 41.66€
         */
        static::assertEquals(50, $cart->getPrice()->getTotalPrice());
        static::assertEquals(50, $cart->getPrice()->getPositionPrice());
        static::assertEquals(41.66, $cart->getPrice()->getNetPrice());

        $promotion = $cart->getLineItems()->getElements();
        $promotion = array_values($promotion)[1];

        static::assertInstanceOf(LineItem::class, $promotion);
        $price = $promotion->getPrice();
        static::assertInstanceOf(CalculatedPrice::class, $price);
        static::assertEquals(-50, $price->getTotalPrice());
        static::assertNotNull($price->getCalculatedTaxes()->first());
        static::assertEquals(-8.33, $price->getCalculatedTaxes()->first()->getTax());
    }

    /**
     * This test verifies that we can set a
     * maximum absolute value for a percentage discount.
     * We have a 100 EUR product and 50% OFF but a maximum
     * of 30 EUR discount. This means our cart should be minimum 70 EUR in the end.
     * We have
     *
     * @group promotions
     */
    public function test50PercentageDiscountWithMaximumValue(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        // add a new sample product
        $this->createTestFixtureProduct($productId, 100, 20, $this->getContainer(), $context);

        // add a new promotion with 50% discount but a maximum of 30 EUR.
        // our product costs 100 EUR, which should now be 70 EUR due to the threshold
        $this->createTestFixturePercentagePromotion($promotionId, $code, 50, 30.0, $this->getContainer());

        $cart = $this->cartService->getCart($context->getToken(), $context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $context);

        static::assertEquals(70, $cart->getPrice()->getTotalPrice());
        static::assertEquals(70, $cart->getPrice()->getPositionPrice());
        static::assertEquals(58.33, $cart->getPrice()->getNetPrice());
    }

    /**
     * This test verifies that we use the max value of our currency
     * instead of the global max value, if existing.
     * Thus we create a promotion with 50% for a 100 EUR price.
     * That would lead to 50 EUR for the product, which we avoid by setting
     * a max global threshold of 40 EUR.
     * But for your currency, we use 30 EUR instead.
     * Our test needs to verify that we use 30 EUR, and end with a product sum of 70 EUR in the end.
     *
     * @group promotions
     */
    public function test50PercentageDiscountWithMaximumValueAndCurrencies(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $productGross = 100;
        $percentage = 50;
        $maxValueGlobal = 40.0;
        $currencyMaxValue = 30.0;

        $expectedPrice = $productGross - $currencyMaxValue;

        // add a new sample product
        $this->createTestFixtureProduct($productId, $productGross, 19, $this->getContainer(), $context);

        $discountId = $this->createTestFixturePercentagePromotion($promotionId, $code, $percentage, $maxValueGlobal, $this->getContainer());

        $this->createTestFixtureAdvancedPrice($discountId, Defaults::CURRENCY, $currencyMaxValue, $this->getContainer());

        $cart = $this->cartService->getCart($context->getToken(), $context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $context);

        static::assertEquals($expectedPrice, $cart->getPrice()->getPositionPrice());
        static::assertEquals($expectedPrice, $cart->getPrice()->getTotalPrice());
        static::assertEquals(58.82, $cart->getPrice()->getNetPrice());
    }

    /**
     * This test verifies that we have fixed our division by zero problem in percentage calculations.
     * That case is happening in very rare scenarios where somehow the
     * product total sum is 0,00 but we still have a promotion that will be calculated.
     * We fake a product with 0,00 price and just try to add our promotion in here.
     * We must not get a division by zero!
     *
     * @group promotions
     *
     * @ticket NEXT-4146
     */
    public function testPercentagePromotionDivisionByZero(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        // add a new sample product
        $this->createTestFixtureProduct($productId, 0, 19, $this->getContainer(), $context);
        // add a new percentage promotion
        $this->createTestFixturePercentagePromotion($promotionId, $code, 100.0, 100.0, $this->getContainer());

        $cart = $this->cartService->getCart($context->getToken(), $context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $context);

        // make sure we have only 1 product line item in there
        static::assertCount(1, $cart->getLineItems());

        // now just try to see if we have a valid 0,00 total price
        static::assertEquals(0, $cart->getPrice()->getTotalPrice());
    }
}
