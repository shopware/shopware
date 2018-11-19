<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Order;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\StateMachine\StateMachineRegistry;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Symfony\Component\HttpFoundation\Response;

class OrderActionControllerTest extends TestCase
{
    use AdminApiTestBehaviour, IntegrationTestBehaviour;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    /**
     * @var RepositoryInterface
     */
    private $orderRepository;

    /**
     * @var RepositoryInterface
     */
    private $customerRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->stateMachineRegistry = $this->getContainer()->get(StateMachineRegistry::class);
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
    }

    public function testOrderNotFoundException(): void
    {
        $this->getClient()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/order/20080911ffff4fffafffffff19830531/actions/state');

        $response = $this->getClient()->getResponse()->getContent();
        $response = json_decode($response, true);

        static::assertEquals(Response::HTTP_NOT_FOUND, $this->getClient()->getResponse()->getStatusCode());
        static::assertArrayHasKey('errors', $response);
    }

    public function testGetAvailableStates(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer($context);
        $orderId = $this->createOrder($customerId, $context);

        $this->getClient()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/order/' . $orderId . '/actions/state');

        $response = $this->getClient()->getResponse()->getContent();
        $response = json_decode($response, true);

        static::assertNotNull($response['currentState']);
        static::assertEquals(Defaults::ORDER_STATE_STATES_OPEN, $response['currentState']['technicalName']);

        static::assertCount(2, $response['transitions']);
        static::assertEquals('cancel', $response['transitions'][0]['actionName']);
        static::assertStringEndsWith('/order/' . $orderId . '/actions/state/cancel', $response['transitions'][0]['url']);
    }

    public function testTransitionToAllowedState(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer($context);
        $orderId = $this->createOrder($customerId, $context);

        $this->getClient()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/order/' . $orderId . '/actions/state');

        $response = $this->getClient()->getResponse()->getContent();
        $response = json_decode($response, true);

        $actionUrl = $response['transitions'][0]['url'];
        $transitionTechnicalName = $response['transitions'][0]['technicalName'];

        $this->getClient()->request('POST', $actionUrl);

        $response = $this->getClient()->getResponse()->getContent();
        $response = json_decode($response, true);

        static::assertEquals(Response::HTTP_OK, $this->getClient()->getResponse()->getStatusCode());
        static::assertEquals($orderId, $response['data']['id']);

        $stateId = $response['data']['relationships']['state']['data']['id'] ?? null;
        static::assertNotNull($stateId);

        $actualTechnicalName = null;

        foreach ($response['included'] as $relationship) {
            if ($relationship['type'] === StateMachineStateDefinition::getEntityName()) {
                $actualTechnicalName = $relationship['attributes']['technicalName'];
                break;
            }
        }

        static::assertEquals($transitionTechnicalName, $actualTechnicalName);
    }

    public function testTransitionToNotAllowedState(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer($context);
        $orderId = $this->createOrder($customerId, $context);

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/order/' . $orderId . '/actions/state/foo');

        $response = $this->getClient()->getResponse()->getContent();
        $response = json_decode($response, true);

        static::assertEquals(Response::HTTP_BAD_REQUEST, $this->getClient()->getResponse()->getStatusCode());
        static::assertArrayHasKey('errors', $response);
    }

    public function testTransitionToEmptyState(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer($context);
        $orderId = $this->createOrder($customerId, $context);

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/order/' . $orderId . '/actions/state');

        $response = $this->getClient()->getResponse()->getContent();
        $response = json_decode($response, true);

        static::assertEquals(Response::HTTP_BAD_REQUEST, $this->getClient()->getResponse()->getStatusCode());
        static::assertArrayHasKey('errors', $response);
    }

    private function createOrder(string $customerId, Context $context): string
    {
        $orderId = Uuid::uuid4()->getHex();
        $stateId = $this->stateMachineRegistry->getInitialState(Defaults::ORDER_STATE_MACHINE, $context)->getId();

        $order = [
            'id' => $orderId,
            'date' => (new \DateTime())->format(Defaults::DATE_FORMAT),
            'amountTotal' => 100,
            'amountNet' => 100,
            'positionPrice' => 100,
            'shippingTotal' => 5,
            'shippingNet' => 5,
            'isNet' => true,
            'isTaxFree' => true,
            'orderCustomer' => [
                'customerId' => $customerId,
                'email' => 'test@example.com',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
            ],
            'stateId' => $stateId,
            'paymentMethodId' => Defaults::PAYMENT_METHOD_DEBIT,
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'billingAddress' => [
                'salutation' => 'mr',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Ebbinghoff 10',
                'zipcode' => '48624',
                'city' => 'Schöppingen',
                'countryId' => Defaults::COUNTRY,
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
        $customerId = Uuid::uuid4()->getHex();
        $addressId = Uuid::uuid4()->getHex();

        $customer = [
            'id' => $customerId,
            'customerNumber' => '1337',
            'salutation' => 'Herr',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => Uuid::uuid4()->getHex() . '@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => Defaults::PAYMENT_METHOD_INVOICE,
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => Defaults::COUNTRY,
                    'salutation' => 'Herr',
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
