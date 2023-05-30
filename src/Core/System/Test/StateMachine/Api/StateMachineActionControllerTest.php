<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\StateMachine\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopware\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class StateMachineActionControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;
    use CountryAddToSalesChannelTestBehaviour;

    /**
     * @var EntityRepository
     */
    private $orderRepository;

    /**
     * @var EntityRepository
     */
    private $customerRepository;

    /**
     * @var EntityRepository
     */
    private $stateMachineHistoryRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->stateMachineHistoryRepository = $this->getContainer()->get('state_machine_history.repository');
    }

    public function testOrderNotFoundException(): void
    {
        $this->getBrowser()->request('GET', '/api/order/' . Uuid::randomHex() . '/actions/state');

        $response = $this->getBrowser()->getResponse()->getContent();
        static::assertIsString($response);
        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals(Response::HTTP_NOT_FOUND, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertArrayHasKey('errors', $response);
    }

    public function testGetAvailableStates(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer($context);
        $orderId = $this->createOrder($customerId, $context);

        $this->getBrowser()->request('GET', '/api/_action/state-machine/order/' . $orderId . '/state');

        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());
        $response = $this->getBrowser()->getResponse()->getContent();
        static::assertIsString($response);
        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(2, $response['transitions']);
        static::assertEquals('cancel', $response['transitions'][0]['actionName']);
        static::assertStringEndsWith('/_action/state-machine/order/' . $orderId . '/state/cancel', $response['transitions'][0]['url']);
    }

    public function testTransitionToAllowedState(): void
    {
        // TODO
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer($context);
        $orderId = $this->createOrder($customerId, $context);

        $this->getBrowser()->request('GET', '/api/_action/state-machine/order/' . $orderId . '/state');

        $response = $this->getBrowser()->getResponse()->getContent();
        static::assertIsString($response);
        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);

        $actionUrl = $response['transitions'][0]['url'];
        $transitionTechnicalName = $response['transitions'][0]['technicalName'];

        $this->getBrowser()->request('POST', $actionUrl);

        $responseString = $this->getBrowser()->getResponse()->getContent();
        static::assertIsString($responseString);
        $response = json_decode($responseString, true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals(
            Response::HTTP_OK,
            $this->getBrowser()->getResponse()->getStatusCode(),
            $responseString
        );

        $stateId = $response['data']['id'] ?? '';
        static::assertTrue(Uuid::isValid($stateId));

        $destinationStateTechnicalName = $response['data']['attributes']['technicalName'];
        static::assertEquals($transitionTechnicalName, $destinationStateTechnicalName);

        // test whether the state history was written
        $criteria = new Criteria();
        $criteria->addAssociation('fromStateMachineState');
        $criteria->addAssociation('toStateMachineState');

        $history = $this->stateMachineHistoryRepository->search($criteria, $context);

        static::assertCount(1, $history->getElements(), 'Expected history to be written');
        /** @var StateMachineHistoryEntity $historyEntry */
        $historyEntry = array_values($history->getElements())[0];

        $toStateMachineState = $historyEntry->getToStateMachineState();
        static::assertInstanceOf(StateMachineStateEntity::class, $toStateMachineState);
        static::assertEquals($destinationStateTechnicalName, $toStateMachineState->getTechnicalName());

        static::assertEquals($this->getContainer()->get(OrderDefinition::class)->getEntityName(), $historyEntry->getEntityName());

        if (!Feature::isActive('v6.6.0.0')) {
            static::assertEquals($orderId, $historyEntry->getEntityId()['id']);
            static::assertEquals(Defaults::LIVE_VERSION, $historyEntry->getEntityId()['version_id']);

            return;
        }

        static::assertEquals($orderId, $historyEntry->getReferencedId());
        static::assertEquals(Defaults::LIVE_VERSION, $historyEntry->getReferencedVersionId());
    }

    public function testTransitionToNotAllowedState(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer($context);
        $orderId = $this->createOrder($customerId, $context);

        $this->getBrowser()->request('POST', '/api/_action/state-machine/order/' . $orderId . '/state/foo');

        $response = $this->getBrowser()->getResponse()->getContent();
        static::assertIsString($response);
        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals(Response::HTTP_BAD_REQUEST, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertArrayHasKey('errors', $response);
    }

    public function testOrderCartDe(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer($context);

        $cartService = $this->getContainer()->get(CartService::class);

        $options = [
            SalesChannelContextService::LANGUAGE_ID => $this->getDeDeLanguageId(),
            SalesChannelContextService::CUSTOMER_ID => $customerId,
            SalesChannelContextService::SHIPPING_METHOD_ID => $this->createShippingMethod(),
        ];

        $salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, $options);

        $productId = Uuid::randomHex();
        $product = [
            'id' => $productId,
            'productNumber' => $productId,
            'name' => [
                'de-DE' => 'test',
                'en-GB' => 'test',
            ],
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => $salesChannelContext->getSalesChannel()->getId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false],
            ],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 18],
            'manufacturer' => [
                'name' => [
                    'de-DE' => 'test',
                    'en-GB' => 'test',
                ],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$product], $salesChannelContext->getContext());
        $this->addTaxDataToSalesChannel($salesChannelContext, $product['tax']);

        $lineItem = (new ProductLineItemFactory(new PriceDefinitionFactory()))->create(['id' => $productId, 'referencedId' => $productId], $salesChannelContext);

        $cart = $cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

        $cart = $cartService->add($cart, $lineItem, $salesChannelContext);

        static::assertTrue($cart->has($productId));

        $orderId = $cartService->order($cart, $salesChannelContext, new RequestDataBag());

        /** @var EntityRepository $orderRepository */
        $orderRepository = $this->getContainer()->get('order.repository');

        /** @var OrderEntity $order */
        $order = $orderRepository->search(new Criteria([$orderId]), $salesChannelContext->getContext())->first();

        static::assertEquals($order->getLanguageId(), $this->getDeDeLanguageId());
    }

    public function testOrderCartEn(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer($context);

        $cartService = $this->getContainer()->get(CartService::class);

        $options = [
            SalesChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
            SalesChannelContextService::CUSTOMER_ID => $customerId,
            SalesChannelContextService::SHIPPING_METHOD_ID => $this->createShippingMethod(),
        ];

        $salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, $options);

        $productId = Uuid::randomHex();
        $product = [
            'id' => $productId,
            'productNumber' => $productId,
            'name' => [
                'de-DE' => 'test',
                'en-GB' => 'test',
            ],
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false],
            ],
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => $salesChannelContext->getSalesChannel()->getId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 18],
            'manufacturer' => [
                'name' => [
                    'de-DE' => 'test',
                    'en-GB' => 'test',
                ],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$product], $salesChannelContext->getContext());
        $this->addTaxDataToSalesChannel($salesChannelContext, $product['tax']);

        $lineItem = (new ProductLineItemFactory(new PriceDefinitionFactory()))->create(['id' => $productId, 'referencedId' => $productId], $salesChannelContext);

        $cart = $cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

        $cart = $cartService->add($cart, $lineItem, $salesChannelContext);

        static::assertTrue($cart->has($productId));

        $orderId = $cartService->order($cart, $salesChannelContext, new RequestDataBag());

        /** @var EntityRepository $orderRepository */
        $orderRepository = $this->getContainer()->get('order.repository');

        /** @var OrderEntity $order */
        $order = $orderRepository->search(new Criteria([$orderId]), $salesChannelContext->getContext())->first();

        static::assertEquals($order->getLanguageId(), Defaults::LANGUAGE_SYSTEM);
    }

    private function createShippingMethod(): string
    {
        $rule = [
            'id' => Uuid::randomHex(),
            'name' => 'test',
            'priority' => 1,
            'conditions' => [
                ['type' => (new AlwaysValidRule())->getName()],
            ],
        ];

        $this->getContainer()->get('rule.repository')
            ->create([$rule], Context::createDefaultContext());

        $shipping = [
            'id' => Uuid::randomHex(),
            'name' => 'test',
            'active' => true,
            'prices' => [
                [
                    'ruleId' => null,
                    'quantityStart' => 0,
                    'currencyPrice' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 10, 'linked' => false],
                    ],
                ],
            ],
            'availabilityRuleId' => $rule['id'],
            'deliveryTimeId' => $this->getContainer()->get(Connection::class)->fetchOne('SELECT LOWER(HEX(id)) FROm delivery_time LIMIT 1'),
            'salesChannels' => [['id' => TestDefaults::SALES_CHANNEL]],
        ];

        $this->getContainer()->get('shipping_method.repository')
            ->create([$shipping], Context::createDefaultContext());

        return $shipping['id'];
    }

    private function createOrder(string $customerId, Context $context): string
    {
        $orderId = Uuid::randomHex();
        $stateId = $this->getContainer()->get(InitialStateIdLoader::class)->get(OrderStates::STATE_MACHINE);
        $billingAddressId = Uuid::randomHex();

        $order = [
            'id' => $orderId,
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'orderNumber' => Uuid::randomHex(),
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderCustomer' => [
                'customerId' => $customerId,
                'email' => 'test@example.com',
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
            ],
            'stateId' => $stateId,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'billingAddressId' => $billingAddressId,
            'addresses' => [
                [
                    'id' => $billingAddressId,
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                    'countryId' => $this->getValidCountryId(),
                ],
            ],
            'lineItems' => [],
            'deliveries' => [],
            'context' => '{}',
            'payload' => '{}',
        ];

        $this->orderRepository->upsert([$order], $context);

        return $orderId;
    }

    private function createCustomer(Context $context): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();
        $this->addCountriesToSalesChannel();

        $customer = [
            'id' => $customerId,
            'customerNumber' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
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

        $this->customerRepository->upsert([$customer], $context);

        return $customerId;
    }
}
