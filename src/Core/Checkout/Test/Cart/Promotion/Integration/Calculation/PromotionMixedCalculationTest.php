<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Integration\Calculation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionIntegrationTestBehaviour;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionTestFixtureBehaviour;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PromotionMixedCalculationTest extends TestCase
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

    /**
     * @var SalesChannelContext
     */
    private $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->promotionRepository = $this->getContainer()->get('promotion.repository');
        $this->cartService = $this->getContainer()->get(CartService::class);
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
    public function testMixedAbsoluteAndPercentageDiscount(): void
    {
        $productId = Uuid::randomHex();
        $promotionId1 = Uuid::randomHex();
        $promotionId2 = Uuid::randomHex();
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        // add a new sample product
        $this->createTestFixtureProduct($productId, 60, 19, $this->getContainer(), $context);

        // add our existing promotions
        $this->createTestFixtureAbsolutePromotion($promotionId1, 'sale', 20, $this->getContainer());
        $this->createTestFixturePercentagePromotion($promotionId2, '100off', 100, null, $this->getContainer());

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
    public function testRemoveDiscountByCode(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        // add a new sample product
        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer(), $context);

        // add a new promotion black friday
        $this->createTestFixturePercentagePromotion($promotionId, $code, 100, null, $this->getContainer());

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
     * This test verifies that our tax calculation uses the correct distribution of taxes.
     * This should be based on a proportional distribution of the actual quantities
     * that have to be discounted and not the one from the original quantities in the cart.
     * We have 2 items with qty 5 and 3. Due to our group, we have discount quantities of 2 and 3 (group sum should be 5).
     * This quantities have different proportional distribution compared to the original.
     * All our calculators need to use this new distribution within their price collection entries
     * when calculating the price and thus, the tax values.
     * The final assert will ensure that our 2 taxes have the correct values with the
     * new proportional distribution.
     *
     * @group promotions
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     */
    public function testProportionalTaxDistribution(): void
    {
        $productId1 = Uuid::randomHex();
        $productId2 = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF19';

        // add 2 test products
        $this->createTestFixtureProduct($productId1, 119, 19, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($productId2, 107, 7, $this->getContainer(), $this->context);

        // add a new promotion
        $this->createTestFixtureFixedDiscountPromotion($promotionId, 200, PromotionDiscountEntity::SCOPE_SET, $code, $this->getContainer(), $this->context);
        $this->createSetGroupFixture('COUNT', 5, 'PRICE_ASC', $promotionId, $this->getContainer());

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // create first product and add to cart
        $cart = $this->addProduct($productId1, 5, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($productId2, 3, $cart, $this->cartService, $this->context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        /** @var CalculatedPrice $discountPrice */
        $discountPrice = $cart->getLineItems()->getFlat()[2]->getPrice();

        /** @var CalculatedTax $tax1 */
        $tax1 = $discountPrice->getCalculatedTaxes()->getElements()[19]->getTax();

        $tax2 = $discountPrice->getCalculatedTaxes()->getElements()[7]->getTax();

        // our correct values are based on a distribution of 2 + 3 instead of 5 + 3
        static::assertEquals(-24.4, $tax1);
        static::assertEquals(-13.49, $tax2);
    }
}
