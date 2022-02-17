<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopware\Core\Checkout\Customer\Api\CustomerGroupRegistrationActionController;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerGroupRegistrationAccepted;
use Shopware\Core\Checkout\Customer\Event\CustomerGroupRegistrationDeclined;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Content\Test\Flow\FlowActionTestSubscriber;
use Shopware\Core\Content\Test\Flow\TestFlowBusinessEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerGroupRegistrationActionControllerTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;
    use AdminApiTestBehaviour;
    use BasicTestDataBehaviour;

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    private ?EntityRepositoryInterface $flowRepository;

    private FlowActionTestSubscriber $flowActionTestSubscriber;

    private ?EventDispatcherInterface $dispatcher;

    public function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->flowRepository = $this->getContainer()->get('flow.repository');
        $this->flowActionTestSubscriber = new FlowActionTestSubscriber();
        $this->dispatcher = $this->getContainer()->get('event_dispatcher');
        $this->dispatcher->addSubscriber($this->flowActionTestSubscriber);
    }

    public function testAcceptRouteWithoutUser(): void
    {
        $browser = $this->createClient();

        $browser->request('POST', '/api/_action/customer-group-registration/accept/' . Defaults::CURRENCY);
        $json = json_decode($browser->getResponse()->getContent(), true);

        static::assertSame('Cannot find Customers', $json['errors'][0]['detail']);
    }

    public function testAcceptRouteWithoutCustomerId(): void
    {
        $browser = $this->createClient();

        $browser->request('POST', '/api/_action/customer-group-registration/accept');
        $json = json_decode($browser->getResponse()->getContent(), true);

        static::assertSame('customerId or customerIds parameter are missing', $json['errors'][0]['detail']);
    }

    public function testAcceptRouteWithoutRequestedGroup(): void
    {
        $browser = $this->createClient();
        $customer = $this->createCustomer();

        $browser->request('POST', '/api/_action/customer-group-registration/accept/' . $customer);
        $json = json_decode($browser->getResponse()->getContent(), true);

        static::assertSame('User ' . $customer . ' dont have approval', $json['errors'][0]['detail']);
    }

    public function testAccept(): void
    {
        $browser = $this->createClient();
        $customer = $this->createCustomer(true);

        $browser->request('POST', '/api/_action/customer-group-registration/accept/' . $customer);

        $criteria = new Criteria([$customer]);
        $criteria->addAssociation('group');

        /** @var CustomerEntity $customerEntity */
        $customerEntity = $this->getContainer()->get('customer.repository')->search($criteria, Context::createDefaultContext())->first();

        static::assertNull($customerEntity->getRequestedGroupId());
        static::assertSame('foo', $customerEntity->getGroup()->getName());

        static::assertSame(204, $browser->getResponse()->getStatusCode());
    }

    public function testAcceptWithFlowBuilder(): void
    {
        $browser = $this->createClient();
        $customer = $this->createCustomer(true);
        $this->createFlow(CustomerGroupRegistrationAccepted::EVENT_NAME);
        $browser->request('POST', '/api/_action/customer-group-registration/accept/' . $customer);

        static::assertEquals(1, $this->flowActionTestSubscriber->actions['unit_test_action_true'] ?? 0);
        static::assertEquals(0, $this->flowActionTestSubscriber->actions['unit_test_action_false'] ?? 0);
    }

    public function testDeclineWithFlowBuilder(): void
    {
        $browser = $this->createClient();
        $customer = $this->createCustomer(true);
        $this->createFlow(CustomerGroupRegistrationDeclined::EVENT_NAME);
        $browser->request('POST', '/api/_action/customer-group-registration/decline/' . $customer);

        static::assertEquals(1, $this->flowActionTestSubscriber->actions['unit_test_action_true'] ?? 0);
        static::assertEquals(0, $this->flowActionTestSubscriber->actions['unit_test_action_false'] ?? 0);
    }

    public function testAcceptUsingCustomerIds(): void
    {
        $browser = $this->createClient();
        $customer = $this->createCustomer(true);

        $browser->request('POST', '/api/_action/customer-group-registration/accept', [
            'customerIds' => [$customer],
        ]);

        $criteria = new Criteria([$customer]);
        $criteria->addAssociation('group');

        /** @var CustomerEntity $customerEntity */
        $customerEntity = $this->getContainer()->get('customer.repository')->search($criteria, Context::createDefaultContext())->first();

        static::assertNull($customerEntity->getRequestedGroupId());
        static::assertSame('foo', $customerEntity->getGroup()->getName());

        static::assertSame(204, $browser->getResponse()->getStatusCode());
    }

    public function testAcceptInBatch(): void
    {
        $browser = $this->createClient();
        $customerA = $this->createCustomer(true);
        $customerB = $this->createCustomer(true);

        $customerIds = [$customerA, $customerB];

        $browser->request('POST', '/api/_action/customer-group-registration/accept', [
            'customerIds' => $customerIds,
        ]);

        $criteria = new Criteria($customerIds);
        $criteria->addAssociation('group');

        $customerEntities = $this->getContainer()->get('customer.repository')->search($criteria, Context::createDefaultContext())->getElements();

        /** @var CustomerEntity $customerEntity */
        foreach ($customerEntities as $customerEntity) {
            static::assertNull($customerEntity->getRequestedGroupId());
            static::assertSame('foo', $customerEntity->getGroup()->getName());

            static::assertSame(204, $browser->getResponse()->getStatusCode());
        }
    }

    public function testDecline(): void
    {
        $browser = $this->createClient();
        $customer = $this->createCustomer(true);

        $browser->request('POST', '/api/_action/customer-group-registration/decline/' . $customer);

        $criteria = new Criteria([$customer]);
        $criteria->addAssociation('group');

        /** @var CustomerEntity $customerEntity */
        $customerEntity = $this->getContainer()->get('customer.repository')->search($criteria, Context::createDefaultContext())->first();

        static::assertNull($customerEntity->getRequestedGroupId());
        static::assertNotSame('foo', $customerEntity->getGroup()->getName());

        static::assertSame(204, $browser->getResponse()->getStatusCode());
    }

    public function testDeclineInBatch(): void
    {
        $browser = $this->createClient();
        $customerA = $this->createCustomer(true);
        $customerB = $this->createCustomer(true);

        $customerIds = [$customerA, $customerB];

        $browser->request('POST', '/api/_action/customer-group-registration/decline', [
            'customerIds' => $customerIds,
        ]);

        $criteria = new Criteria($customerIds);
        $criteria->addAssociation('group');

        $customerEntities = $this->getContainer()->get('customer.repository')->search($criteria, Context::createDefaultContext())->first();

        /** @var CustomerEntity $customerEntity */
        foreach ($customerEntities as $customerEntity) {
            static::assertNull($customerEntity->getRequestedGroupId());
            static::assertNotSame('foo', $customerEntity->getGroup()->getName());

            static::assertSame(204, $browser->getResponse()->getStatusCode());
        }
    }

    public function testAcceptWithSilentError(): void
    {
        $browser = $this->createClient();
        $customerA = $this->createCustomer(true);
        $customerB = $this->createCustomer();

        $customerIds = [$customerA, $customerB];

        $browser->request('POST', '/api/_action/customer-group-registration/accept', [
            'customerIds' => $customerIds,
        ]);

        $json = json_decode($browser->getResponse()->getContent(), true);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $browser->getResponse()->getStatusCode());
        static::assertSame('User ' . $customerB . ' dont have approval', $json['errors'][0]['detail']);

        $browser->request('POST', '/api/_action/customer-group-registration/accept', [
            'customerIds' => $customerIds,
            'silentError' => true,
        ]);

        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode());

        $criteria = new Criteria($customerIds);
        $criteria->addAssociation('group');

        /** @var CustomerEntity $customerEntity */
        $customerEntity = $this->getContainer()->get('customer.repository')->search($criteria, Context::createDefaultContext())->get($customerA);

        static::assertNull($customerEntity->getRequestedGroupId());
        static::assertSame('foo', $customerEntity->getGroup()->getName());
    }

    public function testDeclineWithSilentError(): void
    {
        $browser = $this->createClient();
        $customerA = $this->createCustomer(true);
        $customerB = $this->createCustomer();

        $customerIds = [$customerA, $customerB];

        $browser->request('POST', '/api/_action/customer-group-registration/decline', [
            'customerIds' => $customerIds,
        ]);

        $json = json_decode($browser->getResponse()->getContent(), true);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $browser->getResponse()->getStatusCode());
        static::assertSame('User ' . $customerB . ' dont have approval', $json['errors'][0]['detail']);

        $browser->request('POST', '/api/_action/customer-group-registration/decline', [
            'customerIds' => $customerIds,
            'silentError' => true,
        ]);

        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode());
    }

    public function testEventDispatchCustomerRegistrationAcceptedEventCorrectLanguage(): void
    {
        $customerId = $this->createCustomer(true);
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $langId = $this->ids->get('langId');
        $listener = static function (CustomerGroupRegistrationAccepted $event) use ($langId): void {
            static::assertSame($langId, $event->getContext()->getLanguageId());
        };
        $dispatcher->addListener(CustomerGroupRegistrationAccepted::class, $listener);

        $result = new CustomerGroupRegistrationActionController(
            $this->customerRepository,
            $dispatcher,
            $this->getContainer()->get(SalesChannelContextRestorer::class)
        );
        $request = new Request();
        // will be remove customerId at version v6.5.0
        $request->attributes->set('customerId', $customerId);
        $request->attributes->set('customerIds', [$customerId]);

        $response = $result->accept($request, $this->ids->context);
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $dispatcher->removeListener(CustomerGroupRegistrationAccepted::class, $listener);
    }

    public function testEventDispatchCustomerRegistrationDeclinedEventCorrectLanguage(): void
    {
        $customerId = $this->createCustomer(true);
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $langId = $this->ids->get('langId');
        $listener = static function (CustomerGroupRegistrationAccepted $event) use ($langId): void {
            static::assertSame($langId, $event->getContext()->getLanguageId());
        };
        $dispatcher->addListener(CustomerGroupRegistrationAccepted::class, $listener);

        $result = new CustomerGroupRegistrationActionController(
            $this->customerRepository,
            $dispatcher,
            $this->getContainer()->get(SalesChannelContextRestorer::class)
        );
        $request = new Request();
        // will be remove customerId at version v6.5.0
        $request->attributes->set('customerId', $customerId);
        $request->attributes->set('customerIds', [$customerId]);

        $response = $result->decline($request, $this->ids->context);
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $dispatcher->removeListener(CustomerGroupRegistrationAccepted::class, $listener);
    }

    private function createFlow(?string $eventName = null): void
    {
        $sequenceId = Uuid::randomHex();

        $this->flowRepository->create([[
            'name' => 'Create Order',
            'eventName' => $eventName ?? TestFlowBusinessEvent::EVENT_NAME,
            'priority' => 10,
            'active' => true,
            'sequences' => [
                [
                    'id' => $sequenceId,
                    'parentId' => null,
                    'ruleId' => $this->ids->create('ruleId'),
                    'actionName' => null,
                    'config' => [],
                    'position' => 1,
                    'rule' => [
                        'id' => $this->ids->create('ruleId'),
                        'name' => 'Test rule',
                        'priority' => 1,
                        'conditions' => [
                            ['type' => (new AlwaysValidRule())->getName()],
                        ],
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'parentId' => $sequenceId,
                    'ruleId' => null,
                    'actionName' => 'unit_test_action_true',
                    'config' => [
                        'tagIds' => [
                            $this->ids->get('tag_id') => 'test tag',
                        ],
                        'entity' => OrderDefinition::ENTITY_NAME,
                    ],
                    'position' => 1,
                    'trueCase' => true,
                ],
                [
                    'id' => Uuid::randomHex(),
                    'parentId' => $sequenceId,
                    'ruleId' => null,
                    'actionName' => 'unit_test_action_false',
                    'config' => [
                        'tagIds' => [
                            $this->ids->get('tag_id2') => 'test tag2',
                        ],
                        'entity' => OrderDefinition::ENTITY_NAME,
                    ],
                    'position' => 2,
                    'trueCase' => false,
                ],
            ],
        ],
        ], Context::createDefaultContext());
    }

    private function createCustomer(bool $requestedGroup = false): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $langId = $this->getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(id)) FROM language WHERE name <> "English"'
        );

        $this->ids->set('langId', $langId);
        $this->customerRepository->create([
            array_merge([
                'id' => $customerId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'languageId' => $this->ids->get('langId'),
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schoöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethod' => [
                    'name' => 'Invoice',
                    'active' => true,
                    'description' => 'Default payment method',
                    'handlerIdentifier' => SyncTestPaymentHandler::class,
                    'salesChannels' => [
                        [
                            'id' => TestDefaults::SALES_CHANNEL,
                        ],
                    ],
                ],
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::randomHex() . '@' . Uuid::randomHex() . '.de',
                'password' => Uuid::randomHex(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ], $requestedGroup ? ['requestedGroup' => ['name' => 'foo']] : []),
        ], $this->ids->context);

        return $customerId;
    }
}
