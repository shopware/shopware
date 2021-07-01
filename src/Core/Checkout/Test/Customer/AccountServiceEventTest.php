<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerChangedPaymentMethodEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\SalesChannel\ChangePaymentMethodRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\LoginRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\LogoutRoute;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;

class AccountServiceEventTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    /**
     * @var AccountService
     */
    private $accountService;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    /**
     * @var LoginRoute
     */
    private $loginRoute;

    /**
     * @var LogoutRoute
     */
    private $logoutRoute;

    /**
     * @var ChangePaymentMethodRoute
     */
    private $changePaymentMethodRoute;

    protected function setUp(): void
    {
        $this->accountService = $this->getContainer()->get(AccountService::class);
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->logoutRoute = $this->getContainer()->get(LogoutRoute::class);
        $this->changePaymentMethodRoute = $this->getContainer()->get(ChangePaymentMethodRoute::class);
        $this->loginRoute = $this->getContainer()->get(LoginRoute::class);

        /** @var AbstractSalesChannelContextFactory $salesChannelContextFactory */
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $this->createCustomer($this->salesChannelContext, 'info@example.com', 'shopware');
    }

    public function testLoginBeforeEventNotDispatchedIfNoCredentialsGiven(): void
    {
        /** @var TraceableEventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $eventDidRun = false;

        $listenerClosure = $this->getEmailListenerClosure($eventDidRun, $this);
        $dispatcher->addListener(CustomerBeforeLoginEvent::class, $listenerClosure);

        $dataBag = new DataBag();
        $dataBag->add([
            'username' => '',
            'password' => 'shopware',
        ]);

        try {
            $this->loginRoute->login($dataBag->toRequestDataBag(), $this->salesChannelContext);
        } catch (BadCredentialsException $e) {
            // nth
        }
        static::assertFalse($eventDidRun, 'Event "' . CustomerBeforeLoginEvent::class . '" did run');

        $eventDidRun = false;

        try {
            $this->accountService->login('', $this->salesChannelContext);
        } catch (BadCredentialsException $e) {
            // nth
        }
        static::assertFalse($eventDidRun, 'Event "' . CustomerBeforeLoginEvent::class . '" did run');

        $dispatcher->removeListener(CustomerBeforeLoginEvent::class, $listenerClosure);
    }

    public function testLoginEventsDispatched(): void
    {
        /** @var TraceableEventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $eventsToTest = [
            CustomerBeforeLoginEvent::class,
            CustomerLoginEvent::class,
        ];

        foreach ($eventsToTest as $eventClass) {
            $eventDidRun = false;

            switch ($eventClass) {
                case CustomerBeforeLoginEvent::class:
                    $listenerClosure = $this->getEmailListenerClosure($eventDidRun, $this);

                    break;
                case CustomerLoginEvent::class:
                default:
                    $listenerClosure = $this->getCustomerListenerClosure($eventDidRun, $this);
            }

            $dispatcher->addListener($eventClass, $listenerClosure);

            $dataBag = new DataBag();
            $dataBag->add([
                'username' => 'info@example.com',
                'password' => 'shopware',
            ]);

            $this->loginRoute->login($dataBag->toRequestDataBag(), $this->salesChannelContext);
            static::assertTrue($eventDidRun, 'Event "' . $eventClass . '" did not run');

            $eventDidRun = false;

            $this->accountService->login('info@example.com', $this->salesChannelContext);
            static::assertTrue($eventDidRun, 'Event "' . $eventClass . '" did not run');

            $dispatcher->removeListener($eventClass, $listenerClosure);
        }
    }

    public function testLogoutEventsDispatched(): void
    {
        $email = 'info@example.com';
        /** @var TraceableEventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $eventDidRun = false;

        $listenerClosure = $this->getCustomerListenerClosure($eventDidRun, $this);
        $dispatcher->addListener(CustomerLogoutEvent::class, $listenerClosure);

        $customer = $this->customerRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('email', $email)),
            $this->salesChannelContext->getContext()
        )->first();

        $this->salesChannelContext->assign(['customer' => $customer]);

        static::assertSame($email, $this->salesChannelContext->getCustomer()->getEmail());

        $this->logoutRoute->logout($this->salesChannelContext, new RequestDataBag());

        static::assertTrue($eventDidRun, 'Event "' . CustomerLogoutEvent::class . '" did not run');

        $dispatcher->removeListener(CustomerLogoutEvent::class, $listenerClosure);
    }

    public function testChangeDefaultPaymentMethod(): void
    {
        $email = 'info@example.com';
        /** @var TraceableEventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $eventDidRun = false;

        $listenerClosure = $this->getCustomerListenerClosure($eventDidRun, $this);
        $dispatcher->addListener(CustomerChangedPaymentMethodEvent::class, $listenerClosure);

        /** @var CustomerEntity $customer */
        $customer = $this->customerRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('email', $email)),
            $this->salesChannelContext->getContext()
        )->first();

        $this->salesChannelContext->assign(['customer' => $customer]);

        static::assertSame($email, $this->salesChannelContext->getCustomer()->getEmail());

        $this->changePaymentMethodRoute->change(
            $customer->getDefaultPaymentMethodId(),
            new RequestDataBag(),
            $this->salesChannelContext,
            $customer
        );
        static::assertTrue($eventDidRun, 'Event "' . CustomerChangedPaymentMethodEvent::class . '" did not run');

        $dispatcher->removeListener(CustomerChangedPaymentMethodEvent::class, $listenerClosure);
    }

    private function getEmailListenerClosure(bool &$eventDidRun, self $phpunit)
    {
        return function ($event) use (&$eventDidRun, $phpunit): void {
            $eventDidRun = true;
            $phpunit->assertSame('info@example.com', $event->getEmail());
        };
    }

    private function getCustomerListenerClosure(bool &$eventDidRun, self $phpunit)
    {
        return function ($event) use (&$eventDidRun, $phpunit): void {
            $eventDidRun = true;
            $phpunit->assertSame('info@example.com', $event->getCustomer()->getEmail());
        };
    }

    private function createCustomer(
        SalesChannelContext $salesChannelContext,
        string $email,
        string $password
    ): void {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $this->customerRepository->create([
            [
                'id' => $customerId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethod' => [
                    'name' => 'Invoice',
                    'description' => 'Default payment method',
                    'handlerIdentifier' => SyncTestPaymentHandler::class,
                    'availabilityRule' => [
                        'id' => Uuid::randomHex(),
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'cartCartAmount',
                                'value' => [
                                    'operator' => '>=',
                                    'amount' => 0,
                                ],
                            ],
                        ],
                    ],
                ],
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $email,
                'password' => $password,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ], $salesChannelContext->getContext());
    }
}
