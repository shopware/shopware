<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerRemoteAddressSubscriber;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(CustomerRemoteAddressSubscriber::class)]
class CustomerRemoteAddressSubscriberTest extends TestCase
{
    public function testEvents(): void
    {
        static::assertSame([
            CustomerLoginEvent::class => 'updateRemoteAddressByLogin',
        ], CustomerRemoteAddressSubscriber::getSubscribedEvents());
    }

    public function testNoRequestThereHappensNothing(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects(static::never())->method('getBool');

        $subscriber = new CustomerRemoteAddressSubscriber(
            $this->createMock(Connection::class),
            new RequestStack(),
            $configService
        );

        $subscriber->updateRemoteAddressByLogin(new CustomerLoginEvent($this->createMock(SalesChannelContext::class), new CustomerEntity(), 'test'));
    }

    public function testNullIpDoesNothing(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects(static::never())->method('getBool');

        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $subscriber = new CustomerRemoteAddressSubscriber(
            $this->createMock(Connection::class),
            $requestStack,
            $configService
        );

        $subscriber->updateRemoteAddressByLogin(new CustomerLoginEvent($this->createMock(SalesChannelContext::class), new CustomerEntity(), 'test'));
    }

    public static function providerIPs(): \Generator
    {
        yield 'enabled, should anonymize' => [
            false,
            '94.31.83.28',
            '94.31.83.0',
        ];

        yield 'enabled, should not anonymize' => [
            true,
            '94.31.83.28',
            '94.31.83.28',
        ];
    }

    #[DataProvider('providerIPs')]
    public function testRequest(bool $anonymize, string $clientIp, string $expectedIp): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects(static::once())->method('getBool')->willReturn($anonymize);

        $requestStack = new RequestStack();

        $request = new Request();
        $request->server->set('REMOTE_ADDR', $clientIp);

        $requestStack->push($request);

        $connection = $this->createMock(Connection::class);

        $connection->expects(static::once())
            ->method('update')
            ->with(
                'customer',
                ['remote_address' => $expectedIp],
                ['id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL)]
            );

        $subscriber = new CustomerRemoteAddressSubscriber(
            $connection,
            $requestStack,
            $configService
        );

        $customer = new CustomerEntity();
        $customer->setUniqueIdentifier(TestDefaults::SALES_CHANNEL);
        $customer->setId(TestDefaults::SALES_CHANNEL);

        $subscriber->updateRemoteAddressByLogin(new CustomerLoginEvent($this->createMock(SalesChannelContext::class), $customer, 'test'));
    }
}
