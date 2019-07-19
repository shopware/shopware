<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Integration;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Event\LineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Event\LineItemRemovedEvent;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Promotion\Subscriber\Storefront\StorefrontCartSubscriber;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionIntegrationTestBehaviour;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionTestFixtureBehaviour;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\DataAbstractionLayer\CallableClass;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SessionTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;

class PromotionSessionCodesTest extends TestCase
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
     * This test verifies that our cart service does correctly
     * add our code to the cart within the session.
     * We do not assert the final price here, only that the code is
     * correctly added
     *
     * @test
     * @group promotions
     */
    public function testAddLineItemAddsToSession()
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $promotionCode = 'BF19';

        // add a new sample product
        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer());

        // add a new promotion black friday
        $this->createTestFixturePercentagePromotion($promotionId, $promotionCode, 100, $this->getContainer());

        /** @var Cart $cart */
        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // add product to cart
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $this->context);

        // add promotion to cart
        $this->addPromotionCode($promotionCode, $cart, $this->cartService, $this->context);

        static::assertEquals($promotionCode, $this->getSessionCodes()[0]);
    }

    /**
     * @group promotions
     */
    public function testAddMultiplePromotionCodes()
    {
        $productId = Uuid::randomHex();
        $promotions = [];
        // If we add more than two promotions we could encounter an infinite recursion if a promotion is added
        // via the cart service in the StorefrontCartSubscriber. For good measure, we add more than three promotions
        // to the cart in this case.
        foreach ([100, 1, 42, 13, 19] as $percentage) {
            $promotion = [];
            $promotion['code'] = strval($percentage);
            $promotion['id'] = Uuid::randomHex();
            $promotion['percentage'] = $percentage;
            $promotions[] = $promotion;
        }

        $dispatcher = $this->getContainer()->get('event_dispatcher');

        // For every promotion we expect exactly one LineItemAddedEvent (plus one for the product)
        $addListener = $this->getMockBuilder(CallableClass::class)->setMethods(['__invoke'])->getMock();
        $addListener->expects(static::exactly(1 + count($promotions)))->method('__invoke');
        $dispatcher->addListener(LineItemAddedEvent::class, $addListener);

        // The promotions should not fire a line item removed event
        $removeListener = $this->getMockBuilder(CallableClass::class)->setMethods(['__invoke'])->getMock();
        $removeListener->expects(static::once())->method('__invoke');
        $dispatcher->addListener(LineItemRemovedEvent::class, $removeListener);

        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer());

        /** @var Cart $cart */
        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        foreach ($promotions as $promotion) {
            $this->createTestFixturePercentagePromotion($promotion['id'], $promotion['code'], $promotion['percentage'], $this->getContainer());
            $cart = $this->addPromotionCode($promotion['code'], $cart, $this->cartService, $this->context);

            static::assertContains($promotion['code'], $this->getSessionCodes());
        }

        //assert that all promotions have been added to the session but not to the cart yet
        static::assertCount(0, $cart->getLineItems());
        static::assertCount(count($promotions), $this->getSessionCodes());

        // LineItemAdded events should be fired here
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $this->context);

        // ... and one LineItemRemovedEvent should follow
        $cart = $this->cartService->remove($cart, $productId, $this->context);
        static::assertCount(0, $cart->getLineItems());
    }

    /**
     * This test verifies that our cart services
     * does also correctly remove the matching code
     * within our session, if existing.
     * We add a product and promotion code, then we grab the promotion
     * line item id and remove it.
     * After that we verify that our code array is empty in our session.
     *
     * @test
     * @group promotions
     */
    public function testDeleteLineItemRemovesFromSession()
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $promotionCode = 'BF19';

        // add a new sample product
        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer());

        // add a new promotion black friday
        $this->createTestFixturePercentagePromotion($promotionId, $promotionCode, 100, $this->getContainer());

        /** @var Cart $cart */
        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // add product to cart
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $this->context);

        // add promotion to cart
        $cart = $this->addPromotionCode($promotionCode, $cart, $this->cartService, $this->context);

        /** @var string $discountId */
        $discountId = array_keys($cart->getLineItems()->getElements())[1];

        $this->cartService->remove($cart, $discountId, $this->context);

        static::assertCount(0, $this->getSessionCodes(), json_encode($this->getSessionCodes()));
    }

    /**
     * This test verifies that a promotion get added again
     * if conditions are met again.
     * If a user adds a promotion by code, this code should be
     * persistent in the cart. So if the promotion gets removes because of
     * a change in our product line items, it should be added automatically
     * again if the product conditions are back.
     * This improves the UX because the user doesn't have to re-enter a code.
     *
     * @test
     * @group promotions
     */
    public function testAutoAddingOfPreviousCodes()
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $promotionCode = 'BF19';

        // add a new sample product
        $this->createTestFixtureProduct($productId, 30, 19, $this->getContainer());

        // add a new promotion with a
        // minimum line item quantity discount rule of 2
        $this->createTestFixturePercentagePromotion($promotionId, $promotionCode, 50, $this->getContainer());

        /** @var Cart $cart */
        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // add product to cart with
        // a total price of more than our minimum price rule
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $this->context);

        static::assertCount(1, $cart->getLineItems());

        // add promotion to cart
        // because we have a price above our rule limit, it should be immediately discounted
        $cart = $this->addPromotionCode($promotionCode, $cart, $this->cartService, $this->context);

        static::assertCount(2, $cart->getLineItems());

        // now remove item again and make sure promotion is gone
        $cart = $this->cartService->remove($cart, $productId, $this->context);

        static::assertCount(0, $cart->getLineItems());

        // add our product again and check if our promotion is back
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $this->context);

        static::assertCount(2, $cart->getLineItems());
    }

    private function getSessionCodes(): array
    {
        /** @var Session $session */
        $session = $this->getContainer()->get('session');

        if (!$session->has(StorefrontCartSubscriber::SESSION_KEY_PROMOTION_CODES)) {
            return [];
        }

        return $session->get(StorefrontCartSubscriber::SESSION_KEY_PROMOTION_CODES);
    }
}
