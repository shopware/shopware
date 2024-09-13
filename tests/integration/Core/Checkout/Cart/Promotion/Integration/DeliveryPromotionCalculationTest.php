<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\Promotion\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\Integration\Traits\Promotion\PromotionIntegrationTestBehaviour;
use Shopware\Core\Test\Integration\Traits\Promotion\PromotionTestFixtureBehaviour;
use Shopware\Core\Test\Integration\Traits\Promotion\ShippingMethodPricesTestBehaviour;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
#[Group('slow')]
class DeliveryPromotionCalculationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PromotionIntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;
    use ShippingMethodPricesTestBehaviour;

    private EntityRepository $promotionRepository;

    private CartService $cartService;

    private string $token;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->promotionRepository = $this->getContainer()->get('promotion.repository');
        $this->token = Uuid::randomHex();
        $this->cartService = $this->getContainer()->get(CartService::class);
        $this->context = $this->getContainer()->get(SalesChannelContextFactory::class)->create($this->token, TestDefaults::SALES_CHANNEL);
    }

    protected function tearDown(): void
    {
        $this->restorePrices($this->connection);
        $this->deletePromotions();
        parent::tearDown();
    }

    /**
     * This test verifies that our absolute promotions are correctly added.
     * We add a product and also an absolute promotion.
     * Our final delivery price should then be as expected.
     *
     * @throws CartException
     */
    #[Group('promotions')]
    public function testAbsoluteDeliveryDiscount(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();

        $this->setNewShippingPrices($this->connection, 100);

        $code = 'BF';

        // add a new sample product
        $this->createTestFixtureProduct($productId, 60, 17, $this->getContainer(), $this->context);

        // add a new promotion black friday
        $this->createTestFixtureDeliveryPromotion($promotionId, PromotionDiscountEntity::TYPE_ABSOLUTE, 10, $this->getContainer(), $this->context, $code);

        $cart = $this->cartService->getCart($this->token, $this->context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 2, $cart, $this->cartService, $this->context);

        static::assertEquals(100, $cart->getShippingCosts()->getTotalPrice(), 'Delivery costs should be 100 in the beginning');

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        static::assertEquals(90, $cart->getShippingCosts()->getTotalPrice());

        static::assertEquals(2, $cart->getDeliveries()->count());
    }

    /**
     * This test verifies that our percentage promotions are correctly added.
     * We add a product and also an percentage promotion.
     * Our final delivery price should then be as expected.
     *
     * @throws CartException
     */
    #[Group('promotions')]
    public function testPercentageDeliveryDiscount(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();

        $this->setNewShippingPrices($this->connection, 100);

        $code = 'BF';

        // add a new sample product
        $this->createTestFixtureProduct($productId, 60, 17, $this->getContainer(), $this->context);

        // add a new promotion black friday
        $this->createTestFixtureDeliveryPromotion($promotionId, PromotionDiscountEntity::TYPE_PERCENTAGE, 30, $this->getContainer(), $this->context, $code);

        $cart = $this->cartService->getCart($this->token, $this->context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 2, $cart, $this->cartService, $this->context);

        static::assertEquals(100, $cart->getShippingCosts()->getTotalPrice(), 'Added only product to cart. Delivery costs should be 100');

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        static::assertEquals(70, $cart->getShippingCosts()->getTotalPrice(), 'Added promotion code to cart. Delivery costs should be 50');

        static::assertEquals(2, $cart->getDeliveries()->count());
    }

    /**
     * This test verifies that our percentage promotions are added automatically.
     * We only add a product and got a auto promotion.
     * Our final delivery price should then be as expected.
     *
     * @throws CartException
     */
    #[Group('promotions')]
    public function testPercentageAutoDeliveryDiscount(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();

        $this->setNewShippingPrices($this->connection, 100);

        // add a new sample product
        $this->createTestFixtureProduct($productId, 60, 17, $this->getContainer(), $this->context);

        // add a new auto promotion
        $this->createTestFixtureDeliveryPromotion($promotionId, PromotionDiscountEntity::TYPE_PERCENTAGE, 50, $this->getContainer(), $this->context, null);

        $cart = $this->cartService->getCart($this->token, $this->context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 2, $cart, $this->cartService, $this->context);
        static::assertEquals(2, $cart->getDeliveries()->count());
        static::assertEquals(2, $cart->getLineItems()->count());
        static::assertEquals(50, $cart->getShippingCosts()->getTotalPrice(), 'Added only product to cart. Delivery costs should be 50');
    }

    /**
     * The combination of auto and code Promotion that have percentage and absolute
     * discounts would discount our shipping costs beneath 0
     * Because we are aware of this fact, shipping costs are 0
     *
     * @throws CartException
     */
    #[Group('promotions')]
    public function testPercentageAbsoluteDeliveryDiscountCombination(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $autoPromotionId = Uuid::randomHex();
        $code = 'BF';

        $this->setNewShippingPrices($this->connection, 100);

        // add a new sample product
        $this->createTestFixtureProduct($productId, 60, 17, $this->getContainer(), $this->context);

        // add a new auto promotion
        $this->createTestFixtureDeliveryPromotion($autoPromotionId, PromotionDiscountEntity::TYPE_ABSOLUTE, 90, $this->getContainer(), $this->context, null);

        // add a new auto promotion
        $this->createTestFixtureDeliveryPromotion($promotionId, PromotionDiscountEntity::TYPE_PERCENTAGE, 20, $this->getContainer(), $this->context, $code);

        $cart = $this->cartService->getCart($this->token, $this->context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 2, $cart, $this->cartService, $this->context);

        static::assertEquals(10, $cart->getShippingCosts()->getTotalPrice(), 'Added only product to cart. Delivery costs should be 10');

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        static::assertEquals(3, $cart->getLineItems()->count());
        static::assertEquals(3, $cart->getDeliveries()->count());

        static::assertEquals(0, $cart->getShippingCosts()->getTotalPrice(), 'Added only product to cart. Delivery costs should be 50');
    }

    /**
     * function tests that an absolute discount may not reduce shipping costs beneath 0
     *
     * @throws Exception
     * @throws CartException
     */
    #[Group('promotions')]
    public function testAbsoluteDeliveryDiscountHigherThanShippingCosts(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();

        $this->setNewShippingPrices($this->connection, 100);

        $code = 'BF';

        // add a new sample product
        $this->createTestFixtureProduct($productId, 60, 17, $this->getContainer(), $this->context);

        // add a new promotion black friday
        $this->createTestFixtureDeliveryPromotion($promotionId, PromotionDiscountEntity::TYPE_ABSOLUTE, 200, $this->getContainer(), $this->context, $code);

        $cart = $this->cartService->getCart($this->token, $this->context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 2, $cart, $this->cartService, $this->context);

        static::assertEquals(100, $cart->getShippingCosts()->getTotalPrice(), 'Delivery costs should be 100 in the beginning');

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);
        static::assertEquals(2, $cart->getDeliveries()->count());
        static::assertEquals(0, $cart->getShippingCosts()->getTotalPrice(), 'Delivery Costs may not be discounted beneath 0!');
    }

    /**
     * function tests that an fixed price discount may not increase shipping costs
     *
     * @throws Exception
     * @throws CartException
     */
    #[Group('promotions')]
    public function testFixedDeliveryDiscountHigherThanShippingCosts(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();

        $this->setNewShippingPrices($this->connection, 100);

        $code = 'BF';

        // add a new sample product
        $this->createTestFixtureProduct($productId, 60, 17, $this->getContainer(), $this->context);

        // add a new promotion black friday
        $this->createTestFixtureDeliveryPromotion($promotionId, PromotionDiscountEntity::TYPE_FIXED_UNIT, 200, $this->getContainer(), $this->context, $code);

        $cart = $this->cartService->getCart($this->token, $this->context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 2, $cart, $this->cartService, $this->context);

        static::assertEquals(100, $cart->getShippingCosts()->getTotalPrice(), 'Delivery costs should be 100 in the beginning');

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        static::assertEquals(100, $cart->getShippingCosts()->getTotalPrice(), 'Delivery Costs may not be discounted beneath 0!');
    }

    /**
     * function tests that an fixed price discount sets shipping costs to the defined price
     *
     * @throws Exception
     * @throws CartException
     */
    #[Group('promotions')]
    public function testFixedDeliveryDiscount(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();

        $this->setNewShippingPrices($this->connection, 100);

        $code = 'BF';

        // add a new sample product
        $this->createTestFixtureProduct($productId, 60, 17, $this->getContainer(), $this->context);

        // add a new promotion black friday
        $this->createTestFixtureDeliveryPromotion($promotionId, PromotionDiscountEntity::TYPE_FIXED_UNIT, 69, $this->getContainer(), $this->context, $code);

        $cart = $this->cartService->getCart($this->token, $this->context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 2, $cart, $this->cartService, $this->context);

        static::assertEquals(100, $cart->getShippingCosts()->getTotalPrice(), 'Delivery costs should be 100 in the beginning');

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        static::assertEquals(69, $cart->getShippingCosts()->getTotalPrice(), 'Delivery Costs may not be discounted beneath 0!');
    }

    /**
     * function tests that an fixed price discount that has currency advanced
     * prices, sets shipping costs to the defined advanced currency price
     *
     * @throws Exception
     * @throws CartException
     */
    #[Group('promotions')]
    public function testFixedDeliveryDiscountWithCurrency(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();

        $shippingCosts = 100;
        $this->setNewShippingPrices($this->connection, $shippingCosts);
        $fixedPrice = 60;
        $currencyPrice = 40;

        $code = 'BF';

        // add a new sample product
        $this->createTestFixtureProduct($productId, 97, 17, $this->getContainer(), $this->context);

        // add a new promotion black friday
        $deliveryId = $this->createTestFixtureDeliveryPromotion($promotionId, PromotionDiscountEntity::TYPE_FIXED_UNIT, $fixedPrice, $this->getContainer(), $this->context, $code);

        $this->createTestFixtureAdvancedPrice($deliveryId, Defaults::CURRENCY, $currencyPrice, $this->getContainer());

        $cart = $this->cartService->getCart($this->token, $this->context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 2, $cart, $this->cartService, $this->context);

        static::assertEquals($shippingCosts, $cart->getShippingCosts()->getTotalPrice(), 'Delivery costs should be 100 in the beginning');

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        static::assertEquals($currencyPrice, $cart->getShippingCosts()->getTotalPrice(), 'Delivery Costs may not be discounted beneath 0!');
    }

    /**
     * function tests that an fixed price discount sets shipping costs to the defined price
     * all other discounts are ignored when fixed price discount is present
     *
     * @throws Exception
     * @throws CartException
     */
    #[Group('promotions')]
    public function testMultipleDeliveryDiscountsWithFixed(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();

        $this->setNewShippingPrices($this->connection, 100);

        $code = 'BF';

        // add a new sample product
        $this->createTestFixtureProduct($productId, 60, 17, $this->getContainer(), $this->context);

        // add a new promotion black friday
        $this->createTestFixtureDeliveryPromotion($promotionId, PromotionDiscountEntity::TYPE_ABSOLUTE, 40, $this->getContainer(), $this->context, $code);

        $this->createTestFixtureDiscount($promotionId, PromotionDiscountEntity::TYPE_PERCENTAGE, PromotionDiscountEntity::SCOPE_DELIVERY, 20, null, $this->getContainer(), $this->context);

        $this->createTestFixtureDiscount($promotionId, PromotionDiscountEntity::TYPE_FIXED_UNIT, PromotionDiscountEntity::SCOPE_DELIVERY, 69, null, $this->getContainer(), $this->context);

        $cart = $this->cartService->getCart($this->token, $this->context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 2, $cart, $this->cartService, $this->context);

        static::assertEquals(100, $cart->getShippingCosts()->getTotalPrice(), 'Delivery costs should be 100 in the beginning');

        static::assertEquals(1, $cart->getLineItems()->count());

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        static::assertEquals(2, $cart->getLineItems()->count());

        static::assertEquals(69, $cart->getShippingCosts()->getTotalPrice(), 'Delivery Costs may not be discounted beneath 0!');
    }

    /**
     * function tests that an fixed price discount sets shipping costs to the defined price
     * all other discount are ignored when fixed price discount is present
     *
     * @throws Exception
     * @throws CartException
     */
    #[Group('promotions

NEXT-21735 - Sometimes has a $reduceValue of 0')]
    #[Group('not-deterministic')]
    public function testMultipleDeliveryDiscountsWithoutFixed(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();

        $this->setNewShippingPrices($this->connection, 100);

        $code = 'BF';

        // add a new sample product
        $this->createTestFixtureProduct($productId, 60, 17, $this->getContainer(), $this->context);

        // add a new promotion black friday
        $this->createTestFixtureDeliveryPromotion($promotionId, PromotionDiscountEntity::TYPE_ABSOLUTE, 40, $this->getContainer(), $this->context, $code);

        $this->createTestFixtureDiscount($promotionId, PromotionDiscountEntity::TYPE_PERCENTAGE, PromotionDiscountEntity::SCOPE_DELIVERY, 20, null, $this->getContainer(), $this->context);

        $cart = $this->cartService->getCart($this->token, $this->context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 2, $cart, $this->cartService, $this->context);

        static::assertEquals(100, $cart->getShippingCosts()->getTotalPrice(), 'Delivery costs should be 100 in the beginning');

        static::assertEquals(1, $cart->getLineItems()->count());

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        static::assertEquals(3, $cart->getLineItems()->count());

        static::assertEquals(40, $cart->getShippingCosts()->getTotalPrice(), 'Delivery Costs may not be discounted beneath 0!');
    }

    /**
     * function tests that if several fixed price discount are collected
     * only one and the best customer discount will be selected
     *
     * @throws Exception
     * @throws CartException
     */
    #[Group('promotions')]
    public function testMultipleFixedPriceDeliveryDiscounts(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();

        $this->setNewShippingPrices($this->connection, 100);

        $code = 'BF';

        // add a new sample product
        $this->createTestFixtureProduct($productId, 60, 17, $this->getContainer(), $this->context);

        // add a new promotion black friday
        $this->createTestFixtureDeliveryPromotion($promotionId, PromotionDiscountEntity::TYPE_FIXED_UNIT, 90, $this->getContainer(), $this->context, $code);

        $this->createTestFixtureDiscount($promotionId, PromotionDiscountEntity::TYPE_FIXED_UNIT, PromotionDiscountEntity::SCOPE_DELIVERY, 20, null, $this->getContainer(), $this->context);

        $this->createTestFixtureDiscount($promotionId, PromotionDiscountEntity::TYPE_FIXED_UNIT, PromotionDiscountEntity::SCOPE_DELIVERY, 50, null, $this->getContainer(), $this->context);

        $cart = $this->cartService->getCart($this->token, $this->context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 2, $cart, $this->cartService, $this->context);

        static::assertEquals(100, $cart->getShippingCosts()->getTotalPrice(), 'Delivery costs should be 100 in the beginning');

        static::assertEquals(1, $cart->getLineItems()->count());

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        static::assertEquals(2, $cart->getLineItems()->count());

        static::assertEquals(2, $cart->getDeliveries()->count());

        static::assertEquals(20, $cart->getShippingCosts()->getTotalPrice(), 'Delivery Costs should be the lowest fixed price!');
    }

    /**
     * This test verifies that we use the global max value of our percentage discount
     * Thus we create a promotion with 50% for a 100 EUR discount.
     * That would lead to 50 EUR for shipping costs, which we avoid by setting
     * a max global threshold of 40 EUR.
     * Our test needs to verify that we use 40 EUR, and end with a shipping cost
     * sum of 60 EUR in the end.
     */
    #[Group('promotions')]
    public function test50PercentageDeliveryDiscountWithMaximumValue(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF19';
        $deliveryCosts = 100;

        $this->setNewShippingPrices($this->connection, $deliveryCosts);

        $productGross = 123;
        $percentage = 50;
        $maxValueGlobal = 40.0;

        $expectedPrice = $deliveryCosts - $maxValueGlobal;
        $expectedTotal = $expectedPrice + $productGross;

        // add a new sample product
        $this->createTestFixtureProduct($productId, $productGross, 19, $this->getContainer(), $this->context);

        $this->createTestFixturePercentagePromotion($promotionId, $code, $percentage, $maxValueGlobal, $this->getContainer(), PromotionDiscountEntity::SCOPE_DELIVERY);

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $this->context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        static::assertEquals($expectedPrice, $cart->getDeliveries()->getShippingCosts()->sum()->getTotalPrice());
        static::assertEquals($expectedTotal, $cart->getPrice()->getTotalPrice());
        static::assertEquals(2, $cart->getLineItems()->count());
        static::assertEquals(2, $cart->getDeliveries()->count());
    }

    /**
     * This test verifies that we use the max value of our currency
     * instead of the global max value, if existing.
     * Thus we create a promotion with 50% for a 100 EUR price.
     * That would lead to 50 EUR for shipping costs, which we avoid by setting
     * a max global threshold of 40 EUR.
     * But for your currency, we use 30 EUR instead.
     * Our test needs to verify that we use 30 EUR, and end with a product sum of 70 EUR in the end.
     */
    #[Group('promotions')]
    public function test50PercentageDeliveryDiscountWithMaximumValueAndCurrencies(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF19';
        $deliveryCosts = 100;

        $this->setNewShippingPrices($this->connection, $deliveryCosts);

        $productGross = 123;
        $percentage = 50;
        $maxValueGlobal = 40.0;
        $currencyMaxValue = 30.0;

        $expectedPrice = $deliveryCosts - $currencyMaxValue;

        // add a new sample product
        $this->createTestFixtureProduct($productId, $productGross, 19, $this->getContainer(), $this->context);

        $discountId = $this->createTestFixturePercentagePromotion($promotionId, $code, $percentage, $maxValueGlobal, $this->getContainer(), PromotionDiscountEntity::SCOPE_DELIVERY);

        $this->createTestFixtureAdvancedPrice($discountId, Defaults::CURRENCY, $currencyMaxValue, $this->getContainer());

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $this->context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        static::assertEquals($expectedPrice, $cart->getDeliveries()->getShippingCosts()->sum()->getTotalPrice());
        static::assertEquals(2, $cart->getLineItems()->count());
        static::assertEquals(2, $cart->getDeliveries()->count());
    }

    /**
     * This test verifies that we use the same tax calculation for our discounts
     * as the delivery costs have (they take them from products)
     */
    #[Group('promotions')]
    public function testMultipleDiscountsWithMultipleTaxProducts(): void
    {
        $productId = Uuid::randomHex();
        $productTwoId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF19';
        $deliveryCosts = 104;

        $this->setNewShippingPrices($this->connection, $deliveryCosts);

        $productGross = 123;
        $percentage = 50;

        // add two new sample product
        $this->createTestFixtureProduct($productId, $productGross, 19, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($productTwoId, $productGross, 7, $this->getContainer(), $this->context);

        $this->createTestFixturePercentagePromotion($promotionId, $code, $percentage, null, $this->getContainer(), PromotionDiscountEntity::SCOPE_DELIVERY);

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $this->context);
        // create product and add to cart
        $cart = $this->addProduct($productTwoId, 1, $cart, $this->cartService, $this->context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        static::assertEquals(52, $cart->getDeliveries()->getShippingCosts()->sum()->getTotalPrice());
        static::assertEquals(3, $cart->getLineItems()->count());
        static::assertEquals(2, $cart->getDeliveries()->count());
        static::assertEquals(5.85, $cart->getDeliveries()->getShippingCosts()->sum()->getCalculatedTaxes()->getAmount());
    }

    /**
     * helper function for deleting our created promotions
     *
     * @throws InconsistentCriteriaIdsException
     */
    private function deletePromotions(): void
    {
        $idSearchResult = $this->promotionRepository->searchIds(new Criteria(), $this->context->getContext());
        $data = [];
        foreach ($idSearchResult->getIds() as $id) {
            $data[]['id'] = $id;
        }

        if (\count($data) === 0) {
            return;
        }
        $this->promotionRepository->delete($data, $this->context->getContext());
    }
}
