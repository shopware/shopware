<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\Promotion\Integration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Shopware\Core\Test\Integration\Traits\Promotion\PromotionIntegrationTestBehaviour;
use Shopware\Core\Test\Integration\Traits\Promotion\PromotionTestFixtureBehaviour;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class PromotionRedemptionOnOrderCancelTest extends TestCase
{
    use CountryAddToSalesChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use PromotionIntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;

    /**
     * @var EntityRepository<PromotionCollection>
     */
    private EntityRepository $promotionRepository;

    private CartService $cartService;

    private Connection $connection;

    private StateMachineRegistry $stateMachineRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->promotionRepository = static::getContainer()->get('promotion.repository');
        $this->cartService = static::getContainer()->get(CartService::class);
        $this->connection = static::getContainer()->get(Connection::class);
        $this->stateMachineRegistry = static::getContainer()->get(StateMachineRegistry::class);
        $this->addCountriesToSalesChannel();
    }

    #[Group('promotions')]
    public function testCancellingAnOrderReleasesThePromotionForTheCustomer(): void
    {
        $promotionId = Uuid::randomHex();
        $customerId = $this->createCustomer();
        $orderId = $this->placeOrderWithPromotion($promotionId, $customerId);

        static::assertSame(
            [$customerId => 1],
            $this->fetchPerCustomerCount($promotionId),
            'the placed order must claim the promotion for the customer'
        );
        static::assertSame(1, $this->fetchOrderCount($promotionId));

        $this->transition($orderId, StateMachineTransitionActions::ACTION_CANCEL);

        static::assertNull(
            $this->fetchPerCustomerCount($promotionId),
            'cancelling the order must release the promotion for the customer'
        );
        static::assertSame(0, $this->fetchOrderCount($promotionId));
    }

    #[Group('promotions')]
    public function testTheReleasedCodeCanBeRedeemedAgain(): void
    {
        $promotionId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $customerId = $this->createCustomer();
        $orderId = $this->placeOrderWithPromotion($promotionId, $customerId, $productId);

        $this->transition($orderId, StateMachineTransitionActions::ACTION_CANCEL);

        $context = $this->createCustomerContext($customerId);
        $cart = $this->cartService->getCart($context->getToken(), $context);
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $context);
        $cart = $this->addPromotionCode('TESTCODE', $cart, $this->cartService, $context);

        static::assertCount(
            1,
            $cart->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE),
            'the promotion of a cancelled order must be redeemable again'
        );
        static::assertNull($cart->getErrors()->get('promotion-not-eligible'));
    }

    #[Group('promotions')]
    public function testCancelReopenCancelKeepsTheCountConsistent(): void
    {
        $promotionId = Uuid::randomHex();
        $customerId = $this->createCustomer();
        $orderId = $this->placeOrderWithPromotion($promotionId, $customerId);

        $this->transition($orderId, StateMachineTransitionActions::ACTION_CANCEL);
        static::assertNull($this->fetchPerCustomerCount($promotionId));
        static::assertSame(0, $this->fetchOrderCount($promotionId));

        $this->transition($orderId, StateMachineTransitionActions::ACTION_REOPEN);
        static::assertSame(
            [$customerId => 1],
            $this->fetchPerCustomerCount($promotionId),
            'reopening the order must claim the promotion again'
        );
        static::assertSame(1, $this->fetchOrderCount($promotionId));

        $this->transition($orderId, StateMachineTransitionActions::ACTION_CANCEL);
        static::assertNull($this->fetchPerCustomerCount($promotionId));
        static::assertSame(0, $this->fetchOrderCount($promotionId), 'the count must not drift below zero');
    }

    /**
     * Documents the accepted trade-off from the review: an order reopened after its promotion was
     * claimed elsewhere over-redeems, until either of the two orders is cancelled again.
     */
    #[Group('promotions')]
    public function testReopeningAnOrderClaimedElsewhereExceedsTheGlobalLimit(): void
    {
        $promotionId = Uuid::randomHex();
        $this->createPromotion($promotionId, maxRedemptionsGlobal: 1);

        $firstOrderId = $this->placeOrder($this->createCustomer());
        $this->transition($firstOrderId, StateMachineTransitionActions::ACTION_CANCEL);

        $secondOrderId = $this->placeOrder($this->createCustomer());
        static::assertSame(1, $this->fetchOrderCount($promotionId));

        $this->transition($firstOrderId, StateMachineTransitionActions::ACTION_REOPEN);

        static::assertSame(
            2,
            $this->fetchOrderCount($promotionId),
            'the reopened order claims the promotion again, exceeding the global limit by one'
        );
        static::assertSame(1, $this->countPromotionLineItems($firstOrderId), 'the reopened order keeps its discount');
        static::assertSame(1, $this->countPromotionLineItems($secondOrderId), 'the second order keeps its discount');

        $this->transition($firstOrderId, StateMachineTransitionActions::ACTION_CANCEL);
        static::assertSame(1, $this->fetchOrderCount($promotionId), 'the absolute recount heals the excess again');
    }

    private function placeOrderWithPromotion(string $promotionId, string $customerId, ?string $productId = null): string
    {
        $this->createPromotion($promotionId, maxRedemptionsPerCustomer: 1);

        return $this->placeOrder($customerId, $productId);
    }

    private function createPromotion(string $promotionId, ?int $maxRedemptionsGlobal = null, ?int $maxRedemptionsPerCustomer = null): void
    {
        $this->promotionRepository->create([[
            'id' => $promotionId,
            'name' => 'Test promotion',
            'active' => true,
            'code' => 'TESTCODE',
            'useCodes' => true,
            'useIndividualCodes' => false,
            'maxRedemptionsGlobal' => $maxRedemptionsGlobal,
            'maxRedemptionsPerCustomer' => $maxRedemptionsPerCustomer,
            'salesChannels' => [
                ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'priority' => 1],
            ],
            'discounts' => [
                [
                    'scope' => PromotionDiscountEntity::SCOPE_CART,
                    'type' => PromotionDiscountEntity::TYPE_ABSOLUTE,
                    'value' => 10,
                    'considerAdvancedRules' => false,
                ],
            ],
        ]], Context::createDefaultContext());
    }

    private function placeOrder(string $customerId, ?string $productId = null): string
    {
        $productId ??= Uuid::randomHex();

        $context = $this->createCustomerContext($customerId);
        $this->createTestFixtureProduct($productId, 119, 19, static::getContainer(), $context);

        $cart = $this->cartService->getCart($context->getToken(), $context);
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $context);
        $cart = $this->addPromotionCode('TESTCODE', $cart, $this->cartService, $context);

        static::assertCount(1, $cart->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE));

        return $this->cartService->order($cart, $context, new RequestDataBag());
    }

    private function transition(string $orderId, string $action): void
    {
        $this->stateMachineRegistry->transition(
            new Transition('order', $orderId, $action, 'stateId'),
            Context::createDefaultContext()
        );

        static::assertSame(
            $action === StateMachineTransitionActions::ACTION_CANCEL ? OrderStates::STATE_CANCELLED : OrderStates::STATE_OPEN,
            $this->fetchOrderState($orderId)
        );
    }

    /**
     * @return array<string, int>|null
     */
    private function fetchPerCustomerCount(string $promotionId): ?array
    {
        $counts = $this->connection->fetchOne(
            'SELECT orders_per_customer_count FROM promotion WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($promotionId)]
        );

        if (!\is_string($counts)) {
            return null;
        }

        return json_decode($counts, true, 512, \JSON_THROW_ON_ERROR);
    }

    private function countPromotionLineItems(string $orderId): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM order_line_item
             WHERE order_id = :orderId AND version_id = :version AND type = :type',
            [
                'orderId' => Uuid::fromHexToBytes($orderId),
                'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'type' => PromotionProcessor::LINE_ITEM_TYPE,
            ]
        );
    }

    private function fetchOrderCount(string $promotionId): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT order_count FROM promotion WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($promotionId)]
        );
    }

    private function fetchOrderState(string $orderId): string
    {
        return (string) $this->connection->fetchOne(
            'SELECT state_machine_state.technical_name
             FROM `order`
                 INNER JOIN state_machine_state ON state_machine_state.id = `order`.state_id
             WHERE `order`.id = :id AND `order`.version_id = :version',
            ['id' => Uuid::fromHexToBytes($orderId), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)]
        );
    }

    private function createCustomerContext(string $customerId): SalesChannelContext
    {
        return static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [SalesChannelContextService::CUSTOMER_ID => $customerId]
        );
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
            'password' => TestDefaults::HASHED_PASSWORD,
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

        static::getContainer()
            ->get('customer.repository')
            ->upsert([$customer], Context::createDefaultContext());

        return $customerId;
    }
}
