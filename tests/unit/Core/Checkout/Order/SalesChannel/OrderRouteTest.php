<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Exception\GuestNotAuthenticatedException;
use Shopware\Core\Checkout\Order\Exception\WrongGuestCredentialsException;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
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
    #[DataProvider('limiterCredentialsProvider')]
    public function testNormalizesGuestLimiterKey(bool $validCredentials): void
    {
        $customer = new CustomerEntity();
        $customer->setGuest(true);
        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setCustomer($customer);
        $orderCustomer->setEmail('guest@example.com');
        $billingAddress = new OrderAddressEntity();
        $billingAddress->setZipcode('12345');
        $order = new OrderEntity();
        $order->setId('order');
        $order->setCreatedAt(new \DateTimeImmutable());
        $order->setOrderCustomer($orderCustomer);
        $order->setBillingAddress($billingAddress);
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), $context->getContext()));
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())->method('ensureAccepted')->with(RateLimiter::GUEST_LOGIN, 'deeplinkcode-10.0.0.1');
        $rateLimiter->expects($validCredentials ? $this->once() : $this->never())->method('reset')->with(RateLimiter::GUEST_LOGIN, 'deeplinkcode-10.0.0.1');
        $route = new OrderRoute($repository, $this->createMock(EntityRepository::class), $rateLimiter, $this->createMock(EventDispatcherInterface::class));

        if (!$validCredentials) {
            $this->expectException(WrongGuestCredentialsException::class);
        }

        $route->load(
            new Request(request: ['email' => 'guest@example.com', 'zipcode' => $validCredentials ? '12345' : 'wrong'], server: ['REMOTE_ADDR' => '10.0.0.1']),
            $context,
            (new Criteria())->addFilter(new EqualsFilter('deepLinkCode', "DEEPLINKCODE \u{00A0}\u{3000}")),
        );
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function limiterCredentialsProvider(): iterable
    {
        yield 'successful authentication resets counter' => [true];
        yield 'failed authentication retains counter' => [false];
    }

    #[DataProvider('deeplinkFilterProvider')]
    public function testWronglyDeeplinkFilter(Filter $filter): void
    {
        $this->expectException(CustomerNotLoggedInException::class);

        $route = new OrderRoute(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(RateLimiter::class),
            $this->createMock(EventDispatcherInterface::class),
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

    /**
     * @param class-string<\Throwable>|null $exception
     */
    #[DataProvider('guestCredentialsProvider')]
    public function testValidateGuestCustomer(?bool $isGuest, mixed $mail, mixed $postalCode, ?string $exception): void
    {
        if ($exception !== null) {
            $this->expectException($exception);
        }

        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setId(Uuid::randomHex());
        $orderCustomer->setEmail('test@example.com');

        if ($isGuest !== null) {
            $customer = new CustomerEntity();
            $customer->setId(Uuid::randomHex());
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

        $searchResult = new EntitySearchResult(
            OrderDefinition::ENTITY_NAME,
            1,
            new OrderCollection([$order]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $orderRepository = $this->createMock(EntityRepository::class);
        $orderRepository->method('search')->willReturn($searchResult);

        $route = new OrderRoute(
            $orderRepository,
            $this->createMock(EntityRepository::class),
            $this->createMock(RateLimiter::class),
            $this->createMock(EventDispatcherInterface::class),
        );

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(null);

        $criteria = (new Criteria())->addFilter(new EqualsFilter('deepLinkCode', 'deepLinkCode'));
        $request = new Request([], ['email' => $mail, 'zipcode' => $postalCode]);

        $responseOrder = $route->load($request, $context, $criteria)->getOrders()->first();

        static::assertSame($order, $responseOrder);
    }

    /**
     * @return iterable<string, array{?bool, mixed, mixed, ?class-string<\Throwable>}>
     */
    public static function guestCredentialsProvider(): iterable
    {
        yield 'no customer' => [null, 'test@example.com', 'AA-345', CustomerNotLoggedInException::class];
        yield 'no guest customer' => [false, 'test@example.com', 'AA-345', CustomerNotLoggedInException::class];
        yield 'no request e-mail' => [true, null, 'AA-345', GuestNotAuthenticatedException::class];
        yield 'no request postal code' => [true, 'test@example.com', null, GuestNotAuthenticatedException::class];
        yield 'wrong e-mail' => [true, 'false@example.com', 'AA-345', WrongGuestCredentialsException::class];
        yield 'wrong postal code' => [true, 'test@example.com', '12345', WrongGuestCredentialsException::class];
        yield 'non-string e-mail' => [true, true, 'AA-345', WrongGuestCredentialsException::class];
        yield 'integer postal code' => [true, 'test@example.com', 12345, WrongGuestCredentialsException::class];
        yield 'array postal code' => [true, 'test@example.com', ['AA-345'], WrongGuestCredentialsException::class];
        yield 'valid guest' => [true, 'test@example.com', 'AA-345', null];
        yield 'valid guest uppercase email' => [true, 'Test@Example.Com', 'AA-345', null];
        yield 'valid guest lowercase postal code' => [true, 'Test@Example.Com', 'aa-345', null];
    }
}
