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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;

/**
 * @internal
 */
#[Package('customer-order')]
class AccountServiceEventTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    private AccountService $accountService;

    private EntityRepository $customerRepository;

    private SalesChannelContext $salesChannelContext;

    private LoginRoute $loginRoute;

    private LogoutRoute $logoutRoute;

    private ChangePaymentMethodRoute $changePaymentMethodRoute;

    protected function setUp(): void
    {
        $this->accountService = $this->getContainer()->get(AccountService::class);
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->logoutRoute = $this->getContainer()->get(LogoutRoute::class);
        $this->changePaymentMethodRoute = $this->getContainer()->get(ChangePaymentMethodRoute::class);
        $this->loginRoute = $this->getContainer()->get(LoginRoute::class);

        /** @var AbstractSalesChannelContextFactory $salesChannelContextFactory */
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $this->createCustomer('shopware', 'info@example.com');
    }

    public function testLoginBeforeEventNotDispatchedIfNoCredentialsGiven(): void
    {
        /** @var TraceableEventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $eventDidRun = false;

        $listenerClosure = $this->getEmailListenerClosure($eventDidRun);
        $this->addEventListener($dispatcher, CustomerBeforeLoginEvent::class, $listenerClosure);

        $dataBag = new DataBag();
        $dataBag->add([
            'username' => '',
            'password' => 'shopware',
        ]);

        try {
            $this->loginRoute->login($dataBag->toRequestDataBag(), $this->salesChannelContext);
        } catch (BadCredentialsException) {
            // nth
        }
        static::assertFalse($eventDidRun, 'Event "' . CustomerBeforeLoginEvent::class . '" did run');

        $eventDidRun = false;

        try {
            $this->accountService->login('', $this->salesChannelContext);
        } catch (BadCredentialsException) {
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
                    $listenerClosure = $this->getEmailListenerClosure($eventDidRun);

                    break;
                case CustomerLoginEvent::class:
                default:
                    $listenerClosure = $this->getCustomerListenerClosure($eventDidRun);
            }

            $this->addEventListener($dispatcher, $eventClass, $listenerClosure);

            $dataBag = new DataBag();
            $dataBag->add([
                'username' => 'info@example.com',
                'password' => 'shopware',
            ]);

            $this->loginRoute->login($dataBag->toRequestDataBag(), $this->salesChannelContext);
            static::assertTrue($eventDidRun, 'Event "' . $eventClass . '" did not run');

            $eventDidRun = false;

            $this->accountService->login('info@example.com', $this->salesChannelContext);
            /** @phpstan-ignore-next-line - $eventDidRun updated value on listener */
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

        $listenerClosure = $this->getCustomerListenerClosure($eventDidRun);
        $this->addEventListener($dispatcher, CustomerLogoutEvent::class, $listenerClosure);

        $customer = $this->customerRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('email', $email)),
            $this->salesChannelContext->getContext()
        )->first();

        $this->salesChannelContext->assign(['customer' => $customer]);

        static::assertNotNull($customer = $this->salesChannelContext->getCustomer());
        static::assertSame($email, $customer->getEmail());

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

        $listenerClosure = $this->getCustomerListenerClosure($eventDidRun);
        $this->addEventListener($dispatcher, CustomerChangedPaymentMethodEvent::class, $listenerClosure);

        /** @var CustomerEntity $customer */
        $customer = $this->customerRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('email', $email)),
            $this->salesChannelContext->getContext()
        )->first();

        $this->salesChannelContext->assign(['customer' => $customer]);

        static::assertNotNull($customer = $this->salesChannelContext->getCustomer());
        static::assertSame($email, $customer->getEmail());

        $this->changePaymentMethodRoute->change(
            $customer->getDefaultPaymentMethodId(),
            new RequestDataBag(),
            $this->salesChannelContext,
            $customer
        );
        static::assertTrue($eventDidRun, 'Event "' . CustomerChangedPaymentMethodEvent::class . '" did not run');

        $dispatcher->removeListener(CustomerChangedPaymentMethodEvent::class, $listenerClosure);
    }

    /**
     * @return callable(CustomerBeforeLoginEvent): void
     */
    private function getEmailListenerClosure(bool &$eventDidRun): callable
    {
        return function (CustomerBeforeLoginEvent $event) use (&$eventDidRun): void {
            $eventDidRun = true;
            static::assertSame('info@example.com', $event->getEmail());
        };
    }

    /**
     * @return callable(CustomerLoginEvent|CustomerLogoutEvent|CustomerChangedPaymentMethodEvent): void
     */
    private function getCustomerListenerClosure(bool &$eventDidRun): callable
    {
        return function (CustomerLoginEvent|CustomerLogoutEvent|CustomerChangedPaymentMethodEvent $event) use (&$eventDidRun): void {
            $eventDidRun = true;
            static::assertSame('info@example.com', $event->getCustomer()->getEmail());
        };
    }
}
