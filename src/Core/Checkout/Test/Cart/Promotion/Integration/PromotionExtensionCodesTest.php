<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Integration;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Rule\LineItemTotalPriceRule;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\Extension\CartExtension;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionIntegrationTestBehaviour;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionTestFixtureBehaviour;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class PromotionExtensionCodesTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;
    use PromotionIntegrationTestBehaviour;
    use CountryAddToSalesChannelTestBehaviour;

    protected EntityRepository $productRepository;

    protected CartService $cartService;

    protected EntityRepository $promotionRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->promotionRepository = $this->getContainer()->get('promotion.repository');
        $this->cartService = $this->getContainer()->get(CartService::class);
        $this->addCountriesToSalesChannel();

        $this->context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        // make sure we always start with a fresh cart
        $this->cartService->createNew($this->context->getToken());
    }

    /**
     * This test verifies that our cart service does correctly
     * add our code to the cart within the extension.
     * We do not assert the final price here, only that the code is
     * correctly added
     *
     * @group promotions
     */
    public function testAddLineItemAddsToExtension(): void
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

        /** @var CartExtension $extension */
        $extension = $cart->getExtension(CartExtension::KEY);

        static::assertTrue($extension->hasCode($promotionCode));
    }

    /**
     * This test verifies that our cart services
     * does also correctly remove the matching code
     * within our extension, if existing.
     * We add a product and promotion code, then we grab the promotion
     * line item id and remove it.
     * After that we verify that our code array is empty in our extension.
     *
     * @group promotions
     */
    public function testDeleteLineItemRemovesExtension(): void
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

        /** @var CartExtension $extension */
        $extension = $cart->getExtension(CartExtension::KEY);

        static::assertCount(0, $extension->getCodes());
    }

    /**
     * This test verifies that we successfully block any promotion
     * that does not have a code but gets removed by the user.
     * In this case the promotion must not be added automatically again and again.
     *
     * @group promotions
     */
    public function testAutoPromotionGetsBlockedWhenDeletingItem(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();

        // add a new sample product
        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer(), $this->context);

        // add a new promotion black friday
        $this->createTestFixturePercentagePromotion($promotionId, null, 100, null, $this->getContainer());

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // add product to cart
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $this->context);

        /** @var string $discountId */
        $discountId = array_keys($cart->getLineItems()->getElements())[1];

        $this->cartService->remove($cart, $discountId, $this->context);

        /** @var CartExtension $extension */
        $extension = $cart->getExtension(CartExtension::KEY);

        static::assertTrue($extension->isPromotionBlocked($promotionId));
    }

    /**
     * This test verifies that we can remove a line item
     * and then add that promotion again. In this case we
     * should have the code again in our extension.
     *
     * @group promotions
     */
    public function testDeleteLineItemAndAddItAgainWorks(): void
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

        /** @var CartExtension $extension */
        $extension = $cart->getExtension(CartExtension::KEY);

        static::assertCount(0, $extension->getCodes());

        $this->addPromotionCode($promotionCode, $cart, $this->cartService, $this->context);

        static::assertCount(1, $extension->getCodes());
    }

    /**
     * @group promotions
     */
    public function testResetCodesAfterOrder(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $promotionCode = 'BF19';

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(
                Uuid::randomHex(),
                TestDefaults::SALES_CHANNEL,
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

        $extension = $cart->getExtension(CartExtension::KEY);
        static::assertInstanceOf(CartExtension::class, $extension);

        $before = $extension->getCodes();
        static::assertNotEmpty($before);

        /** @var string $discountId */
        $discountId = array_keys($cart->getLineItems()->getElements())[1];

        $this->cartService->order($cart, $context, new RequestDataBag());

        $this->cartService->remove($cart, $discountId, $context);

        $after = $extension->getCodes();
        static::assertEmpty($after);
    }

    public function testRecalculatePromotionsWithSkippedPrivilege(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $promotionCode = 'BF19';

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(
                Uuid::randomHex(),
                TestDefaults::SALES_CHANNEL,
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

        $orderId = $this->cartService->order($cart, $context, new RequestDataBag());

        $criteria = (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('transactions')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.positions.orderLineItem')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('deliveries.shippingOrderAddress.countryState');

        /** @var OrderEntity $order */
        $order = $this->getContainer()->get('order.repository')
            ->search($criteria, $context->getContext())
            ->get($orderId);
        static::assertNotNull($order);

        $cart = $this->getContainer()->get(OrderConverter::class)
            ->convertToCart($order, $context->getContext());

        $context->setPermissions([
            PromotionProcessor::SKIP_PROMOTION => true,
        ]);

        $cart = $this->cartService->recalculate($cart, $context);

        static::assertCount(2, $cart->getLineItems());
        $promotion = $cart->getLineItems()->filterType(LineItem::PROMOTION_LINE_ITEM_TYPE);
        static::assertCount(1, $promotion, 'Promotion was removed');
    }

    /**
     * This test verifies that our cart services
     * does also correctly remove the matching code
     * within our extension, if existing AND a fixed discount has been added that
     * is discounting TWO products.
     * We add two products and promotion code, then we grab one promotion discount
     * line item id and remove it.
     * After that we verify that our code array is empty in our extension (both discounts on the
     * two products are removed).
     *
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

        /** @var CartExtension $extension */
        $extension = $cart->getExtension(CartExtension::KEY);

        static::assertCount(0, $extension->getCodes());
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

    public function testUsageOfCodeWithActiveNoCodePromo(): void
    {
        $productCheap = Uuid::randomHex();
        $productExpensive = Uuid::randomHex();
        $promotion1 = Uuid::randomHex();
        $promotion2 = Uuid::randomHex();
        $promotionCode = 'TEST123';

        $this->createTestFixtureProduct($productCheap, 2, 19, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($productExpensive, 200, 19, $this->getContainer(), $this->context);

        // create rule
        $ruleId = Uuid::randomHex();

        $ruleRepository = $this->getContainer()->get('rule.repository');
        $conditionRepository = $this->getContainer()->get('rule_condition.repository');

        $ruleRepository->create([
            [
                'id' => $ruleId,
                'name' => 'Cart >= 200',
                'priority' => 1,
            ],
        ], $this->context->getContext());

        $conditionRepository->create([
            [
                'id' => Uuid::randomHex(),
                'type' => (new LineItemTotalPriceRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'operator' => Rule::OPERATOR_GTE,
                    'amount' => 200,
                ],
            ],
        ], $this->context->getContext());

        $this->createCustomPercentagePromotion($promotion1, 'Promo 1', null, 10, null, [
            'cartRules' => [
                ['id' => $ruleId],
            ],
        ]);
        $this->createCustomPercentagePromotion($promotion2, 'Promo 2', $promotionCode, 10, null, [
            'exclusionIds' => [
                $promotion1,
            ],
        ]);

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // add expensive product to cart, promo 1 should be applied now
        $cart = $this->addProduct($productExpensive, 1, $cart, $this->cartService, $this->context);

        static::assertCount(2, $cart->getLineItems());
        static::assertNotNull($cart->getLineItems()->last());
        static::assertSame('Promo 1', $cart->getLineItems()->last()->getLabel());

        // add promotion to cart
        // because we have another rule that cannot be combined, it is not applied
        $cart = $this->addPromotionCode($promotionCode, $cart, $this->cartService, $this->context);

        static::assertCount(2, $cart->getLineItems());
        static::assertNotNull($cart->getLineItems()->last());
        static::assertSame('Promo 1', $cart->getLineItems()->last()->getLabel());

        // now remove item again and make sure promotion is gone
        $cart = $this->cartService->remove($cart, $productExpensive, $this->context);

        static::assertCount(0, $cart->getLineItems());

        // add cheap product to check if our code will be applied
        $cart = $this->addProduct($productCheap, 1, $cart, $this->cartService, $this->context);

        static::assertCount(2, $cart->getLineItems());
        static::assertNotNull($cart->getLineItems()->last());
        static::assertSame('Promo 2', $cart->getLineItems()->last()->getLabel());

        $cart = $this->addProduct($productExpensive, 1, $cart, $this->cartService, $this->context);

        static::assertCount(3, $cart->getLineItems());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createCustomPercentagePromotion(string $promotionId, string $name, ?string $code, float $percentage, ?float $maxValue, array $data = []): string
    {
        $data = array_merge([
            'id' => $promotionId,
            'name' => $name,
            'active' => true,
            'salesChannels' => [
                ['salesChannelId' => $this->context->getSalesChannel()->getId(), 'priority' => 1],
            ],
        ], $data);

        if ($code !== null) {
            $data['code'] = $code;
            $data['useCodes'] = true;
        }

        $this->createPromotionWithCustomData($data, $this->promotionRepository, $this->context);

        return $this->createTestFixtureDiscount($promotionId, PromotionDiscountEntity::TYPE_PERCENTAGE, PromotionDiscountEntity::SCOPE_CART, $percentage, $maxValue, $this->getContainer(), $this->context);
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
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
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
