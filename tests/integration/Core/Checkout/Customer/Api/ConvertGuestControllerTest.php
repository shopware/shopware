<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Api\ConvertGuestController;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Shopware\Core\Checkout\Customer\SalesChannel\ConvertGuestRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\SendPasswordRecoveryMailRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EventDispatcherBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ConvertGuestController::class)]
class ConvertGuestControllerTest extends TestCase
{
    use EventDispatcherBehaviour;
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private ConvertGuestController $controller;

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private readonly EntityRepository $customerRepository;

    protected function setUp(): void
    {
        $this->customerRepository = $this->getContainer()->get('customer.repository');

        $this->controller = new ConvertGuestController(
            $this->customerRepository,
            $this->getContainer()->get(SalesChannelContextService::class),
            $this->getContainer()->get(ConvertGuestRoute::class),
            $this->getContainer()->get(SendPasswordRecoveryMailRoute::class),
        );
    }

    public function testCannotConvertNotExisingCustomer(): void
    {
        $customerId = Uuid::randomHex();
        $request = new Request();

        static::expectException(CustomerException::class);

        $this->controller->convert($request, Context::createDefaultContext(), $customerId);
    }

    public function testCannotConvertARegisteredCustomer(): void
    {
        $customerId = $this->createCustomer();
        $request = new Request();

        static::expectException(CustomerException::class);

        $this->controller->convert($request, Context::createDefaultContext(), $customerId);
    }

    public function testCannotSendRecoveryWithoutDomainUrl(): void
    {
        $context = Context::createDefaultContext();
        $request = new Request();

        $customerId = $this->createCustomer('test@test.com', guest: true);

        $customer = $this->customerRepository
            ->search(new Criteria([$customerId]), $context)
            ->first();

        static::assertInstanceOf(CustomerEntity::class, $customer);

        $this->removeSalesChannelDomains($customer->getSalesChannelId(), $context);

        static::expectException(CustomerException::class);

        $this->controller->convert($request, $context, $customerId);
    }

    public function testConvertGuestWithPassword(): void
    {
        $request = new Request();
        $request->request->add(['password' => 'password']);

        $customerId = $this->createCustomer(guest: true);

        $caughtEvent = null;
        $this->addEventListener(
            $this->getContainer()->get('event_dispatcher'),
            CustomerAccountRecoverRequestEvent::EVENT_NAME,
            function (CustomerAccountRecoverRequestEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            }
        );

        $this->controller->convert($request, Context::createDefaultContext(), $customerId);

        $customer = $this->customerRepository->search(new Criteria([$customerId]), Context::createDefaultContext())->first();

        static::assertNull($caughtEvent);
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertFalse($customer->getGuest());
        static::assertNotNull($customer->getPassword());
    }

    public function testConvertGuestWithoutPassword(): void
    {
        $request = new Request();
        $customerId = $this->createCustomer('test@test.com', guest: true);

        $caughtEvent = null;
        $this->addEventListener(
            $this->getContainer()->get('event_dispatcher'),
            CustomerAccountRecoverRequestEvent::EVENT_NAME,
            function (CustomerAccountRecoverRequestEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            }
        );

        $this->controller->convert($request, Context::createDefaultContext(), $customerId);

        $customer = $this->customerRepository->search(new Criteria([$customerId]), Context::createDefaultContext())->first();

        static::assertInstanceOf(CustomerAccountRecoverRequestEvent::class, $caughtEvent);
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertFalse($customer->getGuest());
        static::assertNotNull($customer->getPassword());
    }

    private function removeSalesChannelDomains(string $salesChannelId, Context $context): void
    {
        $repository = $this->getContainer()->get('sales_channel_domain.repository');

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));

        $ids = $repository->searchIds($criteria, $context)->getIds();

        if (empty($ids)) {
            return;
        }

        $repository->delete(
            array_map(fn (string $id) => ['id' => $id], $ids),
            $context
        );
    }
}
