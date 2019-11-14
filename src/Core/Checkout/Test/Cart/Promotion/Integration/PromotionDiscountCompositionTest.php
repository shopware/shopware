<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Integration;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionIntegrationTestBehaviour;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionTestFixtureBehaviour;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;

class PromotionDiscountCompositionTest extends TestCase
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
     * This test verifies that we have a correct composition node in the payload.
     * We use this as reference, to know of what products and quantities our discount consists of.
     * The absolute discount needs to contain all products and items in the composition. The price of these
     * composition-products need to be divided individually across all included products.
     * We have a product with price 50 EUR and quantity 3 and another product with price 100 and quantity 1.
     * If we have a absolute discount of 30 EUR, then product one should be referenced with 18 EUR and product 2 with 12 EUR (150 EUR vs. 100 EUR).
     *
     * @test
     * @group promotions
     **/
    public function testCompositionInAbsoluteDiscount(): void
    {
        $productId1 = Uuid::randomHex();
        $productId2 = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF19';

        // add a new sample product
        $this->createTestFixtureProduct($productId1, 50, 19, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($productId2, 100, 19, $this->getContainer(), $this->context);

        // add a new promotion
        $this->createTestFixtureAbsolutePromotion($promotionId, $code, 30, $this->getContainer(), PromotionDiscountEntity::SCOPE_CART);

        /** @var Cart $cart */
        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // create product and add to cart
        $cart = $this->addProduct($productId1, 3, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($productId2, 1, $cart, $this->cartService, $this->context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        // get discount line item
        /** @var LineItem $discountItem */
        $discountItem = $cart->getLineItems()->getFlat()[2];

        static::assertTrue($discountItem->hasPayloadValue('composition'), 'composition node is missing');

        /** @var array $composition */
        $composition = $discountItem->getPayload()['composition'];

        static::assertEquals($productId1, $composition[0]['id']);
        static::assertEquals(3, $composition[0]['quantity']);
        static::assertEquals(18, $composition[0]['discount']);

        static::assertEquals($productId2, $composition[1]['id']);
        static::assertEquals(1, $composition[1]['quantity']);
        static::assertEquals(12, $composition[1]['discount']);
    }

    /**
     * This test verifies that our composition data is correct.
     * We apply a discount of 25% on all items. So every item should appear with its original
     * quantity and the 25% of its original price as discount.
     *
     * @test
     * @group promotions
     **/
    public function testCompositionInPercentageDiscount(): void
    {
        $productId1 = Uuid::randomHex();
        $productId2 = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF19';

        // add a new sample product
        $this->createTestFixtureProduct($productId1, 50, 19, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($productId2, 100, 19, $this->getContainer(), $this->context);

        // add a new promotion
        $this->createTestFixturePercentagePromotion($promotionId, $code, 25, null, $this->getContainer(), PromotionDiscountEntity::SCOPE_CART);

        /** @var Cart $cart */
        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // create product and add to cart
        $cart = $this->addProduct($productId1, 3, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($productId2, 1, $cart, $this->cartService, $this->context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        // get discount line item
        /** @var LineItem $discountItem */
        $discountItem = $cart->getLineItems()->getFlat()[2];

        static::assertTrue($discountItem->hasPayloadValue('composition'), 'composition node is missing');

        /** @var array $composition */
        $composition = $discountItem->getPayload()['composition'];

        static::assertEquals($productId1, $composition[0]['id']);
        static::assertEquals(3, $composition[0]['quantity']);
        static::assertEquals(150 * 0.25, $composition[0]['discount']);

        static::assertEquals($productId2, $composition[1]['id']);
        static::assertEquals(1, $composition[1]['quantity']);
        static::assertEquals(100 * 0.25, $composition[1]['discount']);
    }

    /**
     * This test verifies that our composition data is correct.
     * We apply a discount that sells every item for 10 EUR.
     * We have a product with quantity 3 and total of 150 EUR and another product with 100 EUR.
     * Both our composition entries should have a discount of 120 (-3x10) and 90 EUR (-1x10).
     *
     * @test
     * @group promotions
     **/
    public function testCompositionInFixedUnitDiscount(): void
    {
        $productId1 = Uuid::randomHex();
        $productId2 = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF19';

        // add a new sample product
        $this->createTestFixtureProduct($productId1, 50, 19, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($productId2, 100, 19, $this->getContainer(), $this->context);

        // add a new promotion
        $this->createTestFixtureFixedUnitDiscountPromotion($promotionId, 10, PromotionDiscountEntity::SCOPE_CART, $code, $this->getContainer(), $this->context);

        /** @var Cart $cart */
        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // create product and add to cart
        $cart = $this->addProduct($productId1, 3, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($productId2, 1, $cart, $this->cartService, $this->context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        // get discount line item
        /** @var LineItem $discountItem */
        $discountItem = $cart->getLineItems()->getFlat()[2];

        static::assertTrue($discountItem->hasPayloadValue('composition'), 'composition node is missing');

        /** @var array $composition */
        $composition = $discountItem->getPayload()['composition'];

        static::assertEquals($productId1, $composition[0]['id']);
        static::assertEquals(3, $composition[0]['quantity']);
        static::assertEquals(120, $composition[0]['discount']);

        static::assertEquals($productId2, $composition[1]['id']);
        static::assertEquals(1, $composition[1]['quantity']);
        static::assertEquals(90, $composition[1]['discount']);
    }

    /**
     * This test verifies that our composition data is correct.
     * We apply a discount that sells all item for a total of 70 EUR.
     * We have a product with quantity 3 and total of 150 EUR and another product with 100 EUR.
     * Both our composition entries should have a discount of 108 and 72 EUR which should
     * make the rest of it a total of 70 EUR.
     * The calculation is based on their proportionate distribution.
     *
     * @test
     * @group promotions
     **/
    public function testCompositionInFixedDiscount(): void
    {
        $productId1 = Uuid::randomHex();
        $productId2 = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF19';

        // add a new sample product
        $this->createTestFixtureProduct($productId1, 50, 19, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($productId2, 100, 19, $this->getContainer(), $this->context);

        // add a new promotion
        $this->createTestFixtureFixedDiscountPromotion($promotionId, 70, PromotionDiscountEntity::SCOPE_CART, $code, $this->getContainer(), $this->context);

        /** @var Cart $cart */
        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // create product and add to cart
        $cart = $this->addProduct($productId1, 3, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($productId2, 1, $cart, $this->cartService, $this->context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        // get discount line item
        /** @var LineItem $discountItem */
        $discountItem = $cart->getLineItems()->getFlat()[2];

        static::assertTrue($discountItem->hasPayloadValue('composition'), 'composition node is missing');

        /** @var array $composition */
        $composition = $discountItem->getPayload()['composition'];

        static::assertEquals($productId1, $composition[0]['id']);
        static::assertEquals(3, $composition[0]['quantity']);
        static::assertEquals(108, $composition[0]['discount']);

        static::assertEquals($productId2, $composition[1]['id']);
        static::assertEquals(1, $composition[1]['quantity']);
        static::assertEquals(72, $composition[1]['discount']);
    }
}
