<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Exception\WrongGuestCredentialsException;
use Shopware\Core\Checkout\Order\OrderCollection;
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
    #[DataProvider('guestCredentialsProvider')]
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
    public static function guestCredentialsProvider(): iterable
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
}
