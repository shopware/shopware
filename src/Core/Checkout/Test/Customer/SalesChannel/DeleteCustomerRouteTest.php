<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerDeletedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * @group store-api
 */
#[Package('customer-order')]
class DeleteCustomerRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomerTestTrait;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    private EntityRepository $customerRepository;

    /**
     * @var callable
     */
    private $callbackFn;

    /**
     * @var array<mixed>
     */
    private array $events;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->assignSalesChannelContext($this->browser);

        $this->customerRepository = $this->getContainer()->get('customer.repository');

        $this->callbackFn = function (Event $event): void {
            $this->events[$event::class] = $event;
        };

        $this->events = [];
    }

    public function testNotLoggedIn(): void
    {
        $this->browser
            ->request(
                'DELETE',
                '/store-api/account/customer',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__CUSTOMER_NOT_LOGGED_IN', $response['errors'][0]['code']);
    }

    public function testDeleteAValidCustomer(): void
    {
        /** @var TraceableEventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $this->addEventListener($dispatcher, CustomerDeletedEvent::class, $this->callbackFn);

        static::assertArrayNotHasKey(
            CustomerDeletedEvent::class,
            $this->events,
            'IndexStartEvent was dispatched but should not yet.'
        );

        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $id = $this->createCustomer($password, $email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => $password,
                ]
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        $this->browser
            ->request(
                'DELETE',
                '/store-api/account/customer',
                [
                ]
            );

        static::assertSame(204, $this->browser->getResponse()->getStatusCode());

        $criteria = new Criteria([$id]);
        $customer = $this->customerRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertNull($customer);

        static::assertArrayHasKey(CustomerDeletedEvent::class, $this->events);
        /** @var CustomerDeletedEvent $customerDeletedEvent */
        $customerDeletedEvent = $this->events[CustomerDeletedEvent::class];
        static::assertInstanceOf(CustomerDeletedEvent::class, $customerDeletedEvent);

        $dispatcher->removeListener(CustomerDeletedEvent::class, $this->callbackFn);
    }

    public function testDeleteGuestUser(): void
    {
        $customerId = $this->createCustomer(null, null, true);
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->getLoggedInContextToken($customerId, $this->ids->get('sales-channel')));

        $this->browser
            ->request(
                'DELETE',
                '/store-api/account/customer',
                [
                ]
            );

        static::assertSame(204, $this->browser->getResponse()->getStatusCode());

        $criteria = new Criteria([$customerId]);
        $customer = $this->customerRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertNull($customer);
    }
}
