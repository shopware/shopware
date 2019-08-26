<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Integration;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionIntegrationTestBehaviour;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionTestFixtureBehaviour;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;

class PromotionCalculationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;
    use PromotionIntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var EntityRepositoryInterface
     */
    protected $promotionRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->promotionRepository = $this->getContainer()->get('promotion.repository');
        $this->cartService = $this->getContainer()->get(CartService::class);
    }

    /**
     * This test verifies that our absolute promotions are correctly added.
     * We add a product and also an absolute promotion.
     * Our final price should then be as expected.
     *
     * @test
     * @group promotions
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     */
    public function testAbsoluteDiscount()
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        // add a new sample product
        $this->createTestFixtureProduct($productId, 60, 17, $this->getContainer());

        // add a new promotion black friday
        $this->createTestFixtureAbsolutePromotion($promotionId, $code, 45, $this->getContainer());

        /** @var Cart $cart */
        $cart = $this->cartService->getCart($context->getToken(), $context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 2, $cart, $this->cartService, $context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $context);

        static::assertEquals(75.0, $cart->getPrice()->getTotalPrice());
        static::assertEquals(75.0, $cart->getPrice()->getPositionPrice());
        static::assertEquals(64.1, $cart->getPrice()->getNetPrice());
    }

    /**
     * This test verifies that our promotion components are really involved in our checkout.
     * We add a product to the cart and apply a code for a promotion with 100% discount.
     * Our cart should have a total value of 0,00 in the end.
     *
     * @test
     * @group promotions
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     */
    public function test100PercentageDiscount()
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        // add a new sample product
        $this->createTestFixtureProduct($productId, 29, 17, $this->getContainer());

        // add a new promotion black friday
        $this->createTestFixturePercentagePromotion($promotionId, $code, 100, null, $this->getContainer());

        /** @var Cart $cart */
        $cart = $this->cartService->getCart($context->getToken(), $context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 10, $cart, $this->cartService, $context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $context);

        static::assertEquals(0.0, $cart->getPrice()->getTotalPrice());
        static::assertEquals(0.0, $cart->getPrice()->getPositionPrice());
        static::assertEquals(0.0, $cart->getPrice()->getNetPrice());
    }

    /**
     * This test verifies that our promotion components are really involved in our checkout.
     * We add a product to the cart and apply a code for a promotion with 50% discount.
     * Our cart should have a total value of 15,00 in the end.
     *
     * @test
     * @group promotions
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     */
    public function test50PercentageDiscount()
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        // add a new sample product
        $this->createTestFixtureProduct($productId, 100, 20, $this->getContainer());

        // add a new promotion black friday
        $this->createTestFixturePercentagePromotion($promotionId, $code, 50, null, $this->getContainer());

        /** @var Cart $cart */
        $cart = $this->cartService->getCart($context->getToken(), $context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $context);

        static::assertEquals(50, $cart->getPrice()->getTotalPrice());
        static::assertEquals(50, $cart->getPrice()->getPositionPrice());
        static::assertEquals(41.67, $cart->getPrice()->getNetPrice());
    }

    /**
     * This test verifies that we can set a
     * maximum absolute value for a percentage discount.
     * We have a 100 EUR product and 50% OFF but a maximum
     * of 30 EUR discount. This means our cart should be minimum 70 EUR in the end.
     * We have
     *
     * @test
     * @group promotions
     */
    public function test50PercentageDiscountWithMaximumValue(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        // add a new sample product
        $this->createTestFixtureProduct($productId, 100, 20, $this->getContainer());

        // add a new promotion with 50% discount but a maximum of 30 EUR.
        // our product costs 100 EUR, which should now be 70 EUR due to the threshold
        $this->createTestFixturePercentagePromotion($promotionId, $code, 50, 30.0, $this->getContainer());

        /** @var Cart $cart */
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
     * @test
     * @group promotions
     */
    public function test50PercentageDiscountWithMaximumValueAndCurrencies(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $productGross = 100;
        $percentage = 50;
        $maxValueGlobal = 40.0;
        $currencyMaxValue = 30.0;

        $expectedPrice = $productGross - $currencyMaxValue;

        // add a new sample product
        $this->createTestFixtureProduct($productId, $productGross, 19, $this->getContainer());

        /** @var string $discountId */
        $discountId = $this->createTestFixturePercentagePromotion($promotionId, $code, $percentage, $maxValueGlobal, $this->getContainer());

        $this->createTestFixtureAdvancedPrice($discountId, Defaults::CURRENCY, $currencyMaxValue, $this->getContainer());

        /** @var Cart $cart */
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
     * This test verifies that we get a correct 0,00 final price if we
     * add an absolute promotion of -10 and an additional 100% discount.
     *
     * @test
     * @group promotions
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     */
    public function testAbsoluteAndPercentageDiscount()
    {
        $productId = Uuid::randomHex();
        $promotionId1 = Uuid::randomHex();
        $promotionId2 = Uuid::randomHex();
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        // add a new sample product
        $this->createTestFixtureProduct($productId, 60, 19, $this->getContainer());

        // add our existing promotions
        $this->createTestFixtureAbsolutePromotion($promotionId1, 'sale', 20, $this->getContainer());
        $this->createTestFixturePercentagePromotion($promotionId2, '100off', 100, null, $this->getContainer());

        /** @var Cart $cart */
        $cart = $this->cartService->getCart($context->getToken(), $context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 5, $cart, $this->cartService, $context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode('sale', $cart, $this->cartService, $context);
        $cart = $this->addPromotionCode('100off', $cart, $this->cartService, $context);

        static::assertEquals(0.0, $cart->getPrice()->getTotalPrice());
        static::assertEquals(0.0, $cart->getPrice()->getPositionPrice());
        static::assertEquals(0.0, $cart->getPrice()->getNetPrice());
    }

    /**
     * This test verifies that our promotion components are really involved in our checkout.
     * We add a product to the cart and apply a code for a promotion with a currency dependent discount.
     * The standard value of discount would be 15, but our currency price value is 30
     * Our cart should have a total value of 70,00 (and not 85 as standard) in the end.
     *
     * @test
     * @group promotions
     */
    public function testAbsoluteDiscountWithCurrencyPriceValues(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        // add a new sample product
        $this->createTestFixtureProduct($productId, 100, 19, $this->getContainer());

        $this->createAdvancedCurrencyPriceValuePromotion($promotionId, $code, 15, 30);

        /** @var Cart $cart */
        $cart = $this->cartService->getCart($context->getToken(), $context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $context);

        static::assertEquals(70, $cart->getPrice()->getPositionPrice());
        static::assertEquals(70, $cart->getPrice()->getTotalPrice());
        static::assertEquals(58.82, $cart->getPrice()->getNetPrice());
    }

    /**
     * This test verifies that we have fixed our division by zero problem in percentage calculations.
     * That case is happening in very rare scenarios where somehow the
     * product total sum is 0,00 but we still have a promotion that will be calculated.
     * We fake a product with 0,00 price and just try to add our promotion in here.
     * We must not get a division by zero!
     *
     * @test
     * @group promotions
     * @ticket NEXT-4146
     */
    public function testPercentagePromotionDivisionByZero(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        // add a new sample product
        $this->createTestFixtureProduct($productId, 0, 19, $this->getContainer());
        // add a new percentage promotion
        $this->createTestFixturePercentagePromotion($promotionId, $code, 100.0, 100.0, $this->getContainer());

        /** @var Cart $cart */
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

    /**
     * This test verifies that our promotion components are really involved in our checkout.
     * We add a product to the cart and apply a code for a promotion with a currency dependent discount.
     * The standard value of discount would be 15, but our currency price value is 30
     * Our cart should have a total value of 70,00 (and not 85 as standard) in the end.
     *
     * @test
     * @group promotions
     */
    public function testFixedDiscount(): void
    {
        $productId = Uuid::randomHex();
        $productIdTwo = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        // add a new sample product
        $this->createTestFixtureProduct($productId, 100, 19, $this->getContainer());

        // add second sample product
        $this->createTestFixtureProduct($productIdTwo, 100, 7, $this->getContainer());

        // add a new promotion
        $this->createTestFixtureFixedDiscountPromotion($promotionId, 40, PromotionDiscountEntity::SCOPE_CART, $code, $this->getContainer(), $context);

        /** @var Cart $cart */
        $cart = $this->cartService->getCart($context->getToken(), $context);

        // create first product and add to cart
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $context);

        static::assertCount(2, $cart->getLineItems(), 'We expect two lineItems in cart');

        // create second product and add to cart
        $cart = $this->addProduct($productIdTwo, 1, $cart, $this->cartService, $context);

        static::assertCount(4, $cart->getLineItems(), 'We expect four lineItems in cart');

        static::assertEquals(80, $cart->getPrice()->getPositionPrice());
        static::assertEquals(80, $cart->getPrice()->getTotalPrice());
        static::assertEquals(71.00, $cart->getPrice()->getNetPrice(), 'Products have different tax rates (19 and 7), so the discounts must have same tax rate as the product');

        $firstPromoId = $cart->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE)->first()->getId();

        // and remove again
        $cart = $this->cartService->remove($cart, $firstPromoId, $context);

        static::assertCount(2, $cart->getLineItems(), 'We expect two lineItems in cart');

        static::assertEquals(200, $cart->getPrice()->getPositionPrice());
        static::assertEquals(200, $cart->getPrice()->getTotalPrice());
        static::assertEquals(177.49, $cart->getPrice()->getNetPrice(), 'Products have different tax rates (19 and 7), so the discounts must have same tax rate as the product');
    }

    /**
     * if a automatic fixed price promotion (no code necessary) discount is removed
     * it should be added automatically. => Deletion is not possible
     *
     * @test
     * @group promotions
     */
    public function testRemoveOfFixedPromotionsWithoutCode()
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        // add a new sample product
        $this->createTestFixtureProduct($productId, 100, 19, $this->getContainer());

        // add a new promotion
        $this->createTestFixtureFixedDiscountPromotion($promotionId, 40, PromotionDiscountEntity::SCOPE_CART, null, $this->getContainer(), $context);

        /** @var Cart $cart */
        $cart = $this->cartService->getCart($context->getToken(), $context);

        // create first product and add to cart
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $context);

        static::assertCount(2, $cart->getLineItems(), 'We expect two lineItems in cart');

        static::assertEquals(40, $cart->getPrice()->getPositionPrice());
        static::assertEquals(40, $cart->getPrice()->getTotalPrice());
        static::assertEquals(33.61, $cart->getPrice()->getNetPrice(), 'Discounted cart does not have expected net price');

        $discountLineItem = $cart->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE)->first();
        $discountId = $discountLineItem->getId();

        // and try to remove promotion
        $cart = $this->cartService->remove($cart, $discountId, $context);

        static::assertCount(2, $cart->getLineItems(), 'We expect two lineItems in cart');

        static::assertEquals(40, $cart->getPrice()->getPositionPrice());
        static::assertEquals(40, $cart->getPrice()->getTotalPrice());
        static::assertEquals(33.61, $cart->getPrice()->getNetPrice(), 'Even after promotion delete try it should be present and product should be discounted');
    }

    /**
     * This test verifies that we can successfully remove an added
     * promotion by code and get the original price again.
     *
     * @test
     * @group promotions
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     */
    public function testRemoveDiscountByCode()
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        // add a new sample product
        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer());

        // add a new promotion black friday
        $this->createTestFixturePercentagePromotion($promotionId, $code, 100, null, $this->getContainer());

        /** @var Cart $cart */
        $cart = $this->cartService->getCart($context->getToken(), $context);

        // add product to cart
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $context);

        // add promotion to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $context);

        // and remove again
        $cart = $this->removePromotionCode($code, $cart, $this->cartService, $context);

        static::assertEquals(119.0, $cart->getPrice()->getTotalPrice());
        static::assertEquals(119.0, $cart->getPrice()->getPositionPrice());
        static::assertEquals(100.0, $cart->getPrice()->getNetPrice());
    }

    /**
     * This test verifies that we use the max value of our currency
     * instead of the defined fixed price, if existing.
     * Thus we create a promotion with a fixed price of 80
     * That would lead to 80 EUR for the product
     * But for your currency defined price, we use 65 as fixed price instead.
     * Our test needs to verify that we use 30 EUR, and end with a product sum of 65 EUR in the end.
     *
     * @test
     * @group promotions
     */
    public function testFixedPriceDiscountWithCurrencyPrices(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $productGross = 100;
        $fixedPriceValue = 80;
        $currencyMaxValue = 65.0;

        $expectedPrice = $currencyMaxValue;

        // add a new sample product
        $this->createTestFixtureProduct($productId, $productGross, 19, $this->getContainer());

        /** @var string $discountId */
        $discountId = $this->createTestFixtureFixedDiscountPromotion($promotionId, $fixedPriceValue, PromotionDiscountEntity::SCOPE_CART, $code, $this->getContainer(), $context);

        $this->createTestFixtureAdvancedPrice($discountId, Defaults::CURRENCY, $currencyMaxValue, $this->getContainer());

        /** @var Cart $cart */
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
     * create a promotion with a currency based price value discount.
     */
    private function createAdvancedCurrencyPriceValuePromotion(string $promotionId, string $code, float $discountPrice, float $advancedPrice)
    {
        $discountId = Uuid::randomHex();

        $this->promotionRepository->create(
            [
                [
                    'id' => $promotionId,
                    'name' => 'Black Friday',
                    'active' => true,
                    'code' => $code,
                    'useCodes' => true,
                    'salesChannels' => [
                        ['salesChannelId' => Defaults::SALES_CHANNEL, 'priority' => 1],
                    ],
                    'discounts' => [
                        [
                            'id' => $discountId,
                            'scope' => PromotionDiscountEntity::SCOPE_CART,
                            'type' => PromotionDiscountEntity::TYPE_ABSOLUTE,
                            'value' => $discountPrice,
                            'considerAdvancedRules' => false,
                            'promotionDiscountPrices' => [
                                [
                                    'currencyId' => Defaults::CURRENCY,
                                    'discountId' => $discountId,
                                    'price' => $advancedPrice,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            Context::createDefaultContext()
        );
    }
}
