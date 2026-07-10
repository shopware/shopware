<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLoginRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
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
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
class AccountServiceEventTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    private AccountService $accountService;

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private EntityRepository $customerRepository;

    private SalesChannelContext $salesChannelContext;

    private AbstractLoginRoute $loginRoute;

    private LogoutRoute $logoutRoute;

    private EventDispatcherInterface $dispatcher;

    private bool $eventDidRun = false;

    /**
     * @var \Closure(CustomerBeforeLoginEvent): void
     */
    private \Closure $emailListenerClosure;

    /**
     * @var \Closure(CustomerLoginEvent|CustomerLogoutEvent): void
     */
    private \Closure $customerListenerClosure;

    protected function setUp(): void
    {
        $this->accountService = static::getContainer()->get(AccountService::class);
        $this->customerRepository = static::getContainer()->get('customer.repository');
        $this->logoutRoute = static::getContainer()->get(LogoutRoute::class);
        $this->loginRoute = static::getContainer()->get(LoginRoute::class);
        $this->dispatcher = static::getContainer()->get('event_dispatcher');

        $salesChannelContextFactory = static::getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $this->createCustomer('info@example.com');

        $this->emailListenerClosure = function (CustomerBeforeLoginEvent $event): void {
            $this->eventDidRun = true;
            static::assertSame('info@example.com', $event->getEmail());
        };

        $this->customerListenerClosure = function (CustomerLoginEvent|CustomerLogoutEvent $event): void {
            $this->eventDidRun = true;
            static::assertSame('info@example.com', $event->getCustomer()->getEmail());
        };
    }

    public function testLoginBeforeEventNotDispatchedIfNoCredentialsGivenViaLoginRoute(): void
    {
        $this->addEventListener($this->dispatcher, CustomerBeforeLoginEvent::class, $this->emailListenerClosure);

        $dataBag = new DataBag();
        $dataBag->add([
            'username' => '',
            'password' => 'shopware',
        ]);

        $this->expectExceptionObject(new BadCredentialsException());

        try {
            $this->loginRoute->login($dataBag->toRequestDataBag(), $this->salesChannelContext);
        } finally {
            static::assertFalse($this->eventDidRun, 'Event "' . CustomerBeforeLoginEvent::class . '" did run');
            $this->dispatcher->removeListener(CustomerBeforeLoginEvent::class, $this->emailListenerClosure);
        }
    }

    public function testLoginBeforeEventNotDispatchedIfNoCredentialsGivenViaAccountService(): void
    {
        $this->addEventListener($this->dispatcher, CustomerBeforeLoginEvent::class, $this->emailListenerClosure);

        $this->expectExceptionObject(new BadCredentialsException());

        try {
            $this->accountService->loginByCredentials('', 'shopware', $this->salesChannelContext);
        } finally {
            static::assertFalse($this->eventDidRun, 'Event "' . CustomerBeforeLoginEvent::class . '" did run');
            $this->dispatcher->removeListener(CustomerBeforeLoginEvent::class, $this->emailListenerClosure);
        }
    }

    public function testCustomerBeforeLoginEventDispatchedViaLoginRoute(): void
    {
        $this->addEventListener($this->dispatcher, CustomerBeforeLoginEvent::class, $this->emailListenerClosure);

        $dataBag = new DataBag();
        $dataBag->add([
            'username' => 'info@example.com',
            'password' => 'shopware',
        ]);

        $this->loginRoute->login($dataBag->toRequestDataBag(), $this->salesChannelContext);
        static::assertTrue($this->eventDidRun, 'Event "' . CustomerBeforeLoginEvent::class . '" did not run');

        $this->dispatcher->removeListener(CustomerBeforeLoginEvent::class, $this->emailListenerClosure);
    }

    public function testCustomerBeforeLoginEventDispatchedViaAccountService(): void
    {
        $this->addEventListener($this->dispatcher, CustomerBeforeLoginEvent::class, $this->emailListenerClosure);

        $this->accountService->loginByCredentials('info@example.com', 'shopware', $this->salesChannelContext);
        static::assertTrue($this->eventDidRun, 'Event "' . CustomerBeforeLoginEvent::class . '" did not run');

        $this->dispatcher->removeListener(CustomerBeforeLoginEvent::class, $this->emailListenerClosure);
    }

    public function testCustomerLoginEventDispatchedViaLoginRoute(): void
    {
        $this->addEventListener($this->dispatcher, CustomerLoginEvent::class, $this->customerListenerClosure);

        $dataBag = new DataBag();
        $dataBag->add([
            'username' => 'info@example.com',
            'password' => 'shopware',
        ]);

        $this->loginRoute->login($dataBag->toRequestDataBag(), $this->salesChannelContext);
        static::assertTrue($this->eventDidRun, 'Event "' . CustomerLoginEvent::class . '" did not run');

        $this->dispatcher->removeListener(CustomerLoginEvent::class, $this->customerListenerClosure);
    }

    public function testCustomerLoginEventDispatchedViaAccountService(): void
    {
        $this->addEventListener($this->dispatcher, CustomerLoginEvent::class, $this->customerListenerClosure);

        $this->accountService->loginByCredentials('info@example.com', 'shopware', $this->salesChannelContext);
        static::assertTrue($this->eventDidRun, 'Event "' . CustomerLoginEvent::class . '" did not run');

        $this->dispatcher->removeListener(CustomerLoginEvent::class, $this->customerListenerClosure);
    }

    public function testLogoutEventsDispatched(): void
    {
        $email = 'info@example.com';

        $this->addEventListener($this->dispatcher, CustomerLogoutEvent::class, $this->customerListenerClosure);

        $customer = $this->customerRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('email', $email)),
            $this->salesChannelContext->getContext()
        )->first();

        $this->salesChannelContext->assign(['customer' => $customer]);

        static::assertNotNull($customer = $this->salesChannelContext->getCustomer());
        static::assertSame($email, $customer->getEmail());

        $this->logoutRoute->logout($this->salesChannelContext, new RequestDataBag());

        static::assertTrue($this->eventDidRun, 'Event "' . CustomerLogoutEvent::class . '" did not run');

        $this->dispatcher->removeListener(CustomerLogoutEvent::class, $this->customerListenerClosure);
    }
}
