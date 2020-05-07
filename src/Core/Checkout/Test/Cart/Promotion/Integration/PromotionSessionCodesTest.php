<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Integration;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\Subscriber\Storefront\StorefrontCartSubscriber;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionIntegrationTestBehaviour;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionTestFixtureBehaviour;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Symfony\Component\HttpFoundation\Session\Session;

class PromotionSessionCodesTest extends TestCase
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

        // make sure we always start with a fresh cart
        $this->cartService->createNew($this->context->getToken());
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
    public function testAddLineItemAddsToSession(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $promotionCode = 'BF19';

        // add a new sample product
        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer(), $this->context);

        // add a new promotion black friday
        $this->createTestFixturePercentagePromotion($promotionId, $promotionCode, 100, null, $this->getContainer());

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // add product to cart
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $this->context);

        // add promotion to cart
        $this->addPromotionCode($promotionCode, $cart, $this->cartService, $this->context);

        static::assertEquals($promotionCode, $this->getSessionCodes()[0]);
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
    public function testDeleteLineItemRemovesFromSession(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $promotionCode = 'BF19';

        // add a new sample product
        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer(), $this->context);

        // add a new promotion black friday
        $this->createTestFixturePercentagePromotion($promotionId, $promotionCode, 100, null, $this->getContainer());

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

    public function testResetCodesAfterOrder(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $promotionCode = 'BF19';

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(
                Uuid::randomHex(),
                Defaults::SALES_CHANNEL,
                [SalesChannelContextService::CUSTOMER_ID => $this->createCustomer()]
            );

        // add a new sample product
        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer(), $context);

        // add a new promotion black friday
        $this->createTestFixturePercentagePromotion($promotionId, $promotionCode, 100, null, $this->getContainer());

        $cart = $this->cartService->getCart($context->getToken(), $context);

        // add product to cart
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $context);

        // add promotion to cart
        $cart = $this->addPromotionCode($promotionCode, $cart, $this->cartService, $context);

        static::assertNotEmpty($this->getSessionCodes());

        /** @var string $discountId */
        $discountId = array_keys($cart->getLineItems()->getElements())[1];

        $this->cartService->order($cart, $context);

        $this->cartService->remove($cart, $discountId, $context);

        static::assertEmpty($this->getSessionCodes());
    }

    /**
     * This test verifies that our cart services
     * does also correctly remove the matching code
     * within our session, if existing AND a fixed discount has been added that
     * is discounting TWO products.
     * We add two products and promotion code, then we grab one promotion discount
     * line item id and remove it.
     * After that we verify that our code array is empty in our session (both discounts on the
     * two products are removed).
     *
     * @test
     * @group promotions
     */
    public function testDeleteLineItemFixedDiscountByCode(): void
    {
        $productId = Uuid::randomHex();
        $productTwoId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $promotionCode = 'BF19';

        // add a new sample product
        $this->createTestFixtureProduct($productId, 100, 19, $this->getContainer(), $this->context);

        // add a new sample product
        $this->createTestFixtureProduct($productTwoId, 100, 7, $this->getContainer(), $this->context);

        // add a new promotion black friday
        $this->createTestFixtureFixedDiscountPromotion($promotionId, 30, PromotionDiscountEntity::SCOPE_CART, $promotionCode, $this->getContainer(), $this->context);

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // add product to cart
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $this->context);

        // add product to cart
        $cart = $this->addProduct($productTwoId, 1, $cart, $this->cartService, $this->context);

        // add promotion to cart
        $cart = $this->addPromotionCode($promotionCode, $cart, $this->cartService, $this->context);

        $promotionItems = $cart->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE);

        /** @var string $discountId */
        $discountId = array_keys($promotionItems->getElements())[0];

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
    public function testAutoAddingOfPreviousCodes(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $promotionCode = 'BF19';

        // add a new sample product
        $this->createTestFixtureProduct($productId, 30, 19, $this->getContainer(), $this->context);

        // add a new promotion with a
        // minimum line item quantity discount rule of 2
        $this->createTestFixturePercentagePromotion($promotionId, $promotionCode, 50, null, $this->getContainer());

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

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $this->getContainer()
            ->get('customer.repository')
            ->upsert([$customer], Context::createDefaultContext());

        return $customerId;
    }
}
