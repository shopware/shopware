<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Flow\Dispatching;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopware\Core\Checkout\Cart\Rule\CartVolumeRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemTotalPriceRule;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\Rule\LastNameRule;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\Action\AddOrderTagAction;
use Shopware\Core\Content\Flow\Rule\OrderTagRule;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tag\TagCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('services-settings')]
class FlowExecutorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private CartService $cartService;

    private EntityRepository $productRepository;

    private EntityRepository $orderRepository;

    private EntityRepository $orderTransactionRepository;

    private EntityRepository $flowRepository;

    private EntityRepository $tagRepository;

    private OrderTransactionStateHandler $orderTransactionStateHandler;

    private SalesChannelContext $salesChannelContext;

    private string $customerId;

    protected function setUp(): void
    {
        $this->cartService = $this->getContainer()->get(CartService::class);
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->orderTransactionRepository = $this->getContainer()->get('order_transaction.repository');
        $this->orderTransactionStateHandler = $this->getContainer()->get(OrderTransactionStateHandler::class);
        $this->flowRepository = $this->getContainer()->get('flow.repository');
        $this->tagRepository = $this->getContainer()->get('tag.repository');
        $this->customerId = $this->createCustomer();
        $this->salesChannelContext = $this->createDefaultSalesChannelContext();
    }

    public function testFlowExecutesWithIfSequencesEvaluated(): void
    {
        $ids = new IdsCollection();

        $this->createTags($ids);

        $this->createFlow($ids);

        $this->placeOrder($ids);

        $this->orderRepository->update([
            [
                'id' => $ids->get('order'),
                'tags' => [
                    ['id' => $ids->get('tag-1')],
                ],
            ],
        ], $this->salesChannelContext->getContext());

        $this->productRepository->update([
            (new ProductBuilder($ids, 'product'))->price(50)->build(),
        ], $this->salesChannelContext->getContext());

        $this->changeTransactionStateToPaid($ids->get('order'));

        $criteria = new Criteria([$ids->get('order')]);
        $criteria->addAssociation('tags');

        $order = $this->orderRepository
            ->search($criteria, $this->salesChannelContext->getContext())
            ->first();

        static::assertInstanceOf(OrderEntity::class, $order);
        static::assertInstanceOf(TagCollection::class, $order->getTags());
        static::assertContains($ids->get('tag-1'), $order->getTags()->getIds());
        static::assertContains($ids->get('tag-2'), $order->getTags()->getIds());
    }

    private function placeOrder(IdsCollection $ids): void
    {
        $cart = $this->cartService->createNew($this->salesChannelContext->getToken());
        $cart = $this->addProducts($cart, $ids);

        $ids->set('order', $this->cartService->order($cart, $this->salesChannelContext, new RequestDataBag()));
    }

    private function addProducts(Cart $cart, IdsCollection $ids): Cart
    {
        $taxIds = $this->salesChannelContext->getTaxRules()->getIds();
        $ids->set('t1', (string) array_pop($taxIds));

        $this->productRepository->create([
            (new ProductBuilder($ids, 'product'))
                ->price(100)
                ->tax('t1')
                ->visibility()
                ->add('height', 3000)
                ->add('width', 3000)
                ->add('length', 3000)
                ->build(),
        ], $this->salesChannelContext->getContext());

        return $this->addProductToCart($ids->get('product'), 1, $cart, $this->cartService, $this->salesChannelContext);
    }

    private function changeTransactionStateToPaid(string $orderId): void
    {
        $transaction = $this->orderTransactionRepository
            ->search(
                (new Criteria())
                    ->addFilter(new EqualsFilter('orderId', $orderId))
                    ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING)),
                $this->salesChannelContext->getContext()
            )->first();
        static::assertInstanceOf(OrderTransactionEntity::class, $transaction);

        $this->orderTransactionStateHandler->paid($transaction->getId(), $this->salesChannelContext->getContext());
    }

    private function createTags(IdsCollection $idsCollection): void
    {
        $tags = [
            [
                'id' => $idsCollection->get('tag-1'),
                'name' => 'foo',
            ],
            [
                'id' => $idsCollection->get('tag-2'),
                'name' => 'bar',
            ],
        ];

        $this->tagRepository->create($tags, $this->salesChannelContext->getContext());
    }

    private function createFlow(IdsCollection $idsCollection): void
    {
        $this->flowRepository->create([
            [
                'name' => 'On enter paid state',
                'eventName' => 'state_enter.order_transaction.state.paid',
                'priority' => 10,
                'active' => true,
                'sequences' => [
                    [
                        'id' => $idsCollection->get('sequence-1'),
                        'parentId' => null,
                        'actionName' => null,
                        'config' => [],
                        'position' => 1,
                        'rule' => [
                            'name' => 'Test order rule',
                            'priority' => 1,
                            'conditions' => [
                                [
                                    'type' => (new OrderTagRule())->getName(),
                                    'value' => [
                                        'identifiers' => [$idsCollection->get('tag-1')],
                                        'operator' => OrderTagRule::OPERATOR_EQ,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => $idsCollection->get('sequence-2'),
                        'parentId' => $idsCollection->get('sequence-1'),
                        'actionName' => null,
                        'config' => [],
                        'position' => 1,
                        'trueCase' => true,
                        'rule' => [
                            'name' => 'Test line item rule',
                            'priority' => 1,
                            'conditions' => [
                                [
                                    'type' => (new LineItemRule())->getName(),
                                    'value' => [
                                        'identifiers' => [$idsCollection->get('product')],
                                        'operator' => OrderTagRule::OPERATOR_EQ,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => $idsCollection->get('sequence-3'),
                        'parentId' => $idsCollection->get('sequence-2'),
                        'actionName' => null,
                        'config' => [],
                        'position' => 1,
                        'trueCase' => true,
                        'rule' => [
                            'name' => 'Test customer rule',
                            'priority' => 1,
                            'conditions' => [
                                [
                                    'type' => (new LastNameRule())->getName(),
                                    'value' => [
                                        'lastName' => 'Mustermann',
                                        'operator' => OrderTagRule::OPERATOR_EQ,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => $idsCollection->get('sequence-4'),
                        'parentId' => $idsCollection->get('sequence-3'),
                        'actionName' => null,
                        'config' => [],
                        'position' => 1,
                        'trueCase' => true,
                        'rule' => [
                            'name' => 'Test cart rule',
                            'priority' => 1,
                            'conditions' => [
                                [
                                    'type' => (new CartVolumeRule())->getName(),
                                    'value' => [
                                        'volume' => 8,
                                        'operator' => CartVolumeRule::OPERATOR_GT,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => $idsCollection->get('sequence-5'),
                        'parentId' => $idsCollection->get('sequence-4'),
                        'actionName' => null,
                        'config' => [],
                        'position' => 1,
                        'trueCase' => true,
                        'rule' => [
                            'name' => 'Test price rule',
                            'priority' => 1,
                            'conditions' => [
                                [
                                    'type' => (new LineItemTotalPriceRule())->getName(),
                                    'value' => [
                                        'amount' => 100,
                                        'operator' => LineItemTotalPriceRule::OPERATOR_GTE,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'parentId' => $idsCollection->get('sequence-5'),
                        'ruleId' => null,
                        'actionName' => AddOrderTagAction::getName(),
                        'config' => [
                            'tagIds' => [$idsCollection->get('tag-2') => 'bar'],
                            'entity' => OrderDefinition::ENTITY_NAME,
                        ],
                        'position' => 1,
                        'trueCase' => true,
                    ],
                ],
            ],
        ], $this->salesChannelContext->getContext());
    }

    private function addProductToCart(string $productId, int $quantity, Cart $cart, CartService $cartService, SalesChannelContext $context): Cart
    {
        $factory = new ProductLineItemFactory(new PriceDefinitionFactory());
        $product = $factory->create(['id' => $productId, 'referencedId' => $productId, 'quantity' => $quantity], $context);

        return $cartService->add($cart, $product, $context);
    }

    private function createDefaultSalesChannelContext(): SalesChannelContext
    {
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

        return $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, [SalesChannelContextService::CUSTOMER_ID => $this->customerId]);
    }
}
