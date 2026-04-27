<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\Service\GuestAuthenticator;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Event\OrderCriteriaEvent;
use Shopware\Core\Checkout\Order\Exception\GuestNotAuthenticatedException;
use Shopware\Core\Checkout\Order\Exception\WrongGuestCredentialsException;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderRoute::class)]
class OrderRouteTest extends TestCase
{
    public function testNotLoggedIn(): void
    {
        $this->expectException(OrderException::class);

        $route = new OrderRoute(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(RateLimiter::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(AccountService::class),
            new GuestAuthenticator(),
        );

        $route->load(new Request(), $this->createMock(SalesChannelContext::class), new Criteria());
    }

    public function testLoadCustomerOrder(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $context = $this->createMock(SalesChannelContext::class);
        $context
            ->method('getCustomer')
            ->willReturn($customer);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $event): object {
                static::assertInstanceOf(OrderCriteriaEvent::class, $event);

                return $event;
            });

        $searchResult = new EntitySearchResult(
            OrderDefinition::ENTITY_NAME,
            1,
            new OrderCollection([$order]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $orderRepository = $this->createMock(EntityRepository::class);
        $orderRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $route = new OrderRoute(
            $orderRepository,
            $this->createMock(EntityRepository::class),
            $this->createMock(RateLimiter::class),
            $eventDispatcher,
            $this->createMock(AccountService::class),
            new GuestAuthenticator(),
        );

        $responseOrder = $route->load(new Request(), $context, new Criteria())->getOrders()->first();

        static::assertNotNull($responseOrder);
        static::assertSame($order->getId(), $responseOrder->getId());
    }

    /**
     * @param ?class-string<\Throwable> $exception
     */
    #[DataProvider('customerDataProvider')]
    public function testValidateGuestCustomer(?bool $isGuest, ?string $mail, ?string $postalCode, ?string $exception, bool $login = false): void
    {
        if ($exception !== null) {
            $this->expectException($exception);
        }

        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setId(Uuid::randomHex());
        $orderCustomer->setEmail('test@example.com');
        $orderCustomer->setCustomerId(Uuid::randomHex());

        if ($isGuest !== null) {
            $customer = new CustomerEntity();
            $customer->setId($orderCustomer->getId());
            $customer->setGuest($isGuest);

            $orderCustomer->setCustomer($customer);
        }

        $billingAddress = new OrderAddressEntity();
        $billingAddress->setZipcode('AA-345');

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setCreatedAt(new \DateTime());
        $order->setOrderCustomer($orderCustomer);
        $order->setBillingAddress($billingAddress);

        $context = $this->createMock(SalesChannelContext::class);
        $context
            ->method('getCustomer')
            ->willReturn(null);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $event): object {
                static::assertInstanceOf(OrderCriteriaEvent::class, $event);

                return $event;
            });

        $searchResult = new EntitySearchResult(
            OrderDefinition::ENTITY_NAME,
            1,
            new OrderCollection([$order]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $orderRepository = $this->createMock(EntityRepository::class);
        $orderRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $accountService = $this->createMock(AccountService::class);
        $accountService->expects($login ? $this->once() : $this->never())
            ->method('loginById')
            ->with($orderCustomer->getCustomerId())
            ->willReturn('newContextToken');

        $route = new OrderRoute(
            $orderRepository,
            $this->createMock(EntityRepository::class),
            $this->createMock(RateLimiter::class),
            $eventDispatcher,
            $accountService,
            new GuestAuthenticator(),
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('deepLinkCode', 'deepLinkCode'));

        $request = new Request();
        $request->request->set('email', $mail);
        $request->request->set('zipcode', $postalCode);
        $request->request->set('login', $login);

        $response = $route->load($request, $context, $criteria);
        $responseOrder = $response->getOrders()->first();

        static::assertNotNull($responseOrder);
        static::assertSame($order->getId(), $responseOrder->getId());
        static::assertSame($login ? 'newContextToken' : null, $response->headers->get('sw-context-token'));
    }

    /**
     * @return array<string, array{?bool, ?string, ?string, ?class-string<\Throwable>}>
     */
    public static function customerDataProvider(): array
    {
        return [
            'no customer' => [null, 'test@example.com', 'AA-345', CustomerException::class],
            'no guest customer' => [false, 'test@example.com', 'AA-345', CustomerException::class],
            'no request e-mail' => [true, null, 'AA-345', GuestNotAuthenticatedException::class],
            'no request postal code' => [true, 'test@example.com', null, GuestNotAuthenticatedException::class],
            'wrong e-mail' => [true, 'false@example.com', 'AA-345', WrongGuestCredentialsException::class],
            'wrong postal code' => [true, 'test@example.com', '12345', WrongGuestCredentialsException::class],
            'valid guest' => [true, 'test@example.com', 'AA-345', null],
            'valid guest uppercase email' => [true, 'Test@Example.Com', 'AA-345', null],
            'valid guest lowercase postal code' => [true, 'Test@Example.Com', 'aa-345', null],
            'valid guest with login' => [true, 'Test@Example.Com', 'aa-345', null, true],
        ];
    }

    #[DataProvider('deeplinkFilterProvider')]
    public function testWronglyDeeplinkFilter(Filter $filter): void
    {
        $this->expectException(OrderException::class);

        $route = new OrderRoute(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(RateLimiter::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(AccountService::class),
            new GuestAuthenticator(),
        );

        $route->load(new Request(), $this->createMock(SalesChannelContext::class), (new Criteria())->addFilter($filter));
    }

    /**
     * @return array<string, array{Filter}>
     */
    public static function deeplinkFilterProvider(): array
    {
        return [
            'deeplink equalsAny' => [new EqualsAnyFilter('deepLinkCode', ['deepLinkCode'])],
            'deeplink multi' => [new MultiFilter(MultiFilter::CONNECTION_OR, [new EqualsFilter('deepLinkCode', 'deepLinkCode')])],
            'deeplink not' => [new NotFilter(MultiFilter::CONNECTION_OR, [new EqualsFilter('deepLinkCode', 'deepLinkCode')])],
            'deeplink suffix' => [new SuffixFilter('deepLinkCode', 'Code')],
            'deeplink prefix' => [new PrefixFilter('deepLinkCode', 'deep')],
        ];
    }

    #[DataProvider('deeplinkExpireDaysProvider')]
    public function testDeeplinkFilterExpireDays(int $createdDaysAgo, int $expireDays, bool $expectedFiltered): void
    {
        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setId(Uuid::randomHex());
        $orderCustomer->setEmail('test@example.com');
        $orderCustomer->setCustomerId(Uuid::randomHex());

        $guestCustomer = new CustomerEntity();
        $guestCustomer->setId($orderCustomer->getId());
        $guestCustomer->setGuest(true);
        $orderCustomer->setCustomer($guestCustomer);

        $billingAddress = new OrderAddressEntity();
        $billingAddress->setZipcode('AA-345');

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setCreatedAt(new \DateTime("-{$createdDaysAgo} days"));
        $order->setUpdatedAt(new \DateTime("-{$createdDaysAgo} days"));
        $order->setOrderCustomer($orderCustomer);
        $order->setBillingAddress($billingAddress);

        $context = $this->createMock(SalesChannelContext::class);
        $context
            ->method('getCustomer')
            ->willReturn(null);

        $searchResult = new EntitySearchResult(
            OrderDefinition::ENTITY_NAME,
            1,
            new OrderCollection([$order]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $orderRepository = $this->createMock(EntityRepository::class);
        $orderRepository
            ->method('search')
            ->willReturn($searchResult);

        $route = new OrderRoute(
            $orderRepository,
            $this->createMock(EntityRepository::class),
            $this->createMock(RateLimiter::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(AccountService::class),
            new GuestAuthenticator(),
            $expireDays
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('deepLinkCode', 'someDeepLinkCode'));

        $request = new Request();
        $request->request->set('email', 'test@example.com');
        $request->request->set('zipcode', 'AA-345');

        if ($expectedFiltered) {
            $this->expectException(OrderException::class);
        }

        $response = $route->load($request, $context, $criteria);

        if (!$expectedFiltered) {
            static::assertSame($order->getId(), $response->getOrders()->first()?->getId());
        }
    }

    /**
     * @return array<string, array{int, int, bool}>
     */
    public static function deeplinkExpireDaysProvider(): array
    {
        return [
            'order within limit' => [10, 30, false],
            'order beyond limit' => [31, 30, true],
            'order beyond default, within custom limit' => [40, 60, false],
            'order beyond custom limit' => [61, 60, true],
        ];
    }
}
