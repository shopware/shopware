<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Integration;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionIntegrationTestBehaviour;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionTestFixtureBehaviour;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SessionTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;

class PromotionCalculationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;
    use PromotionIntegrationTestBehaviour;
    use SessionTestBehaviour;

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

    /**
     * @var \Shopware\Core\System\SalesChannel\SalesChannelContext
     */
    private $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->promotionRepository = $this->getContainer()->get('promotion.repository');
        $this->cartService = $this->getContainer()->get(CartService::class);

        $this->context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
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
        $code = 'BF19';

        // add a new sample product
        $this->createTestFixtureProduct($productId, 60, 17, $this->getContainer());

        // add a new promotion black friday
        $this->createTestFixtureAbsolutePromotion($promotionId, $code, 45, $this->getContainer());

        /** @var Cart $cart */
        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 2, $cart, $this->cartService, $this->context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

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
        $code = 'BF19';

        // add a new sample product
        $this->createTestFixtureProduct($productId, 29, 17, $this->getContainer());

        // add a new promotion black friday
        $this->createTestFixturePercentagePromotion($promotionId, $code, 100, $this->getContainer());

        /** @var Cart $cart */
        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 10, $cart, $this->cartService, $this->context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

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
        $code = 'BF19';

        // add a new sample product
        $this->createTestFixtureProduct($productId, 30, 17, $this->getContainer());

        // add a new promotion black friday
        $this->createTestFixturePercentagePromotion($promotionId, $code, 50, $this->getContainer());

        /** @var Cart $cart */
        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $this->context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        static::assertEquals(15, $cart->getPrice()->getTotalPrice());
        static::assertEquals(15, $cart->getPrice()->getPositionPrice());
        static::assertEquals(12.82, $cart->getPrice()->getNetPrice());
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

        // add a new sample product
        $this->createTestFixtureProduct($productId, 60, 19, $this->getContainer());

        // add our existing promotions
        $this->createTestFixtureAbsolutePromotion($promotionId1, 'sale', 20, $this->getContainer());
        $this->createTestFixturePercentagePromotion($promotionId2, '100off', 100, $this->getContainer());

        /** @var Cart $cart */
        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 5, $cart, $this->cartService, $this->context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode('sale', $cart, $this->cartService, $this->context);
        $cart = $this->addPromotionCode('100off', $cart, $this->cartService, $this->context);

        static::assertEquals(0.0, $cart->getPrice()->getTotalPrice());
        static::assertEquals(0.0, $cart->getPrice()->getPositionPrice());
        static::assertEquals(0.0, $cart->getPrice()->getNetPrice());
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
        $code = 'BF19';

        // add a new sample product
        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer());

        // add a new promotion black friday
        $this->createTestFixturePercentagePromotion($promotionId, $code, 100, $this->getContainer());

        /** @var Cart $cart */
        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // add product to cart
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $this->context);

        // add promotion to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        // and remove again
        $cart = $this->removePromotionCode($code, $cart, $this->cartService, $this->context);

        static::assertEquals(119.0, $cart->getPrice()->getTotalPrice());
        static::assertEquals(119.0, $cart->getPrice()->getPositionPrice());
        static::assertEquals(100.0, $cart->getPrice()->getNetPrice());
    }
}
