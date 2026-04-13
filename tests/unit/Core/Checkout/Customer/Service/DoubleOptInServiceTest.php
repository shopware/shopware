<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopware\Core\Checkout\Customer\Event\DoubleOptInGuestOrderEvent;
use Shopware\Core\Checkout\Customer\Service\DoubleOptInService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DoubleOptInService::class)]
class DoubleOptInServiceTest extends TestCase
{
    private MockObject&SystemConfigService $systemConfigService;

    private EventDispatcher $eventDispatcher;

    /**
     * @var StaticEntityRepository<CustomerCollection>
     */
    private StaticEntityRepository $customerRepository;

    /**
     * @var StaticEntityRepository<SalesChannelDomainCollection>
     */
    private StaticEntityRepository $salesChannelDomainRepository;

    private DoubleOptInService $service;

    protected function setUp(): void
    {
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->eventDispatcher = new EventDispatcher();
        $this->customerRepository = new StaticEntityRepository([]);
        $this->salesChannelDomainRepository = new StaticEntityRepository([]);

        $this->service = new DoubleOptInService(
            $this->customerRepository,
            $this->eventDispatcher,
            $this->systemConfigService,
            $this->salesChannelDomainRepository,
        );
    }

    public function testSendDoubleOptInMailDispatchesRegistrationEvent(): void
    {
        $customer = $this->createCustomerEntity('testhash', false);
        $context = Generator::generateSalesChannelContext();

        $this->systemConfigService->method('getString')->willReturn('');

        $dispatched = null;
        $this->eventDispatcher->addListener(
            CustomerDoubleOptInRegistrationEvent::class,
            static function (CustomerDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->service->sendDoubleOptInMail($customer, $context, 'https://shop.example.com');

        static::assertInstanceOf(CustomerDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringStartsWith('https://shop.example.com', $dispatched->getConfirmUrl());
    }

    public function testSendDoubleOptInMailDispatchesGuestEvent(): void
    {
        $customer = $this->createCustomerEntity('testhash', true);
        $context = Generator::generateSalesChannelContext();

        $this->systemConfigService->method('getString')->willReturn('');

        $dispatched = null;
        $this->eventDispatcher->addListener(
            DoubleOptInGuestOrderEvent::class,
            static function (DoubleOptInGuestOrderEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->service->sendDoubleOptInMail($customer, $context, 'https://shop.example.com');

        static::assertInstanceOf(DoubleOptInGuestOrderEvent::class, $dispatched);
    }

    public function testSendDoubleOptInMailWithRedirectTo(): void
    {
        $customer = $this->createCustomerEntity('testhash', false);
        $context = Generator::generateSalesChannelContext();

        $this->systemConfigService->method('getString')->willReturn('');

        $dispatched = null;
        $this->eventDispatcher->addListener(
            CustomerDoubleOptInRegistrationEvent::class,
            static function (CustomerDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->service->sendDoubleOptInMail($customer, $context, 'https://shop.example.com', 'account');

        static::assertInstanceOf(CustomerDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringContainsString('redirectTo=account', $dispatched->getConfirmUrl());
    }

    public function testSendDoubleOptInMailWithRedirectToAndParameters(): void
    {
        $customer = $this->createCustomerEntity('testhash', false);
        $context = Generator::generateSalesChannelContext();

        $this->systemConfigService->method('getString')->willReturn('');

        $dispatched = null;
        $this->eventDispatcher->addListener(
            CustomerDoubleOptInRegistrationEvent::class,
            static function (CustomerDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->service->sendDoubleOptInMail(
            $customer,
            $context,
            'https://shop.example.com',
            'frontend.account.order.single.page',
            '{"orderId":"abc123"}'
        );

        static::assertInstanceOf(CustomerDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringContainsString('redirectTo=frontend.account.order.single.page', $dispatched->getConfirmUrl());
        static::assertStringContainsString('orderId=abc123', $dispatched->getConfirmUrl());
    }

    public function testSendDoubleOptInMailUsesCustomConfirmUrl(): void
    {
        $customer = $this->createCustomerEntity('customhash', false);
        $context = Generator::generateSalesChannelContext();

        $this->systemConfigService->method('getString')
            ->willReturn('/custom/confirm?em=%%HASHEDEMAIL%%&hash=%%SUBSCRIBEHASH%%');

        $dispatched = null;
        $this->eventDispatcher->addListener(
            CustomerDoubleOptInRegistrationEvent::class,
            static function (CustomerDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->service->sendDoubleOptInMail($customer, $context, 'https://shop.example.com');

        static::assertInstanceOf(CustomerDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringContainsString('/custom/confirm', $dispatched->getConfirmUrl());
        static::assertStringContainsString('customhash', $dispatched->getConfirmUrl());
    }

    public function testResendDoubleOptInMailDisabledWhenIntervalIsZero(): void
    {
        $customer = $this->createCustomerEntity('testhash', false);
        $customer->setDoubleOptInEmailSentDate(new \DateTimeImmutable('-10 days'));
        $context = Generator::generateSalesChannelContext();

        $this->systemConfigService->method('getInt')->willReturn(0);

        $eventDispatched = false;
        $this->eventDispatcher->addListener(
            CustomerDoubleOptInRegistrationEvent::class,
            static function () use (&$eventDispatched): void {
                $eventDispatched = true;
            }
        );

        $this->service->resendDoubleOptInMail($customer, $context);

        static::assertFalse($eventDispatched);
        static::assertEmpty($this->customerRepository->updates);
    }

    public function testResendDoubleOptInMailSkipsWhenNoSentDate(): void
    {
        $customer = $this->createCustomerEntity('testhash', false);
        // no doubleOptInEmailSentDate set
        $context = Generator::generateSalesChannelContext();

        $this->systemConfigService->method('getInt')->willReturn(86400);

        $eventDispatched = false;
        $this->eventDispatcher->addListener(
            CustomerDoubleOptInRegistrationEvent::class,
            static function () use (&$eventDispatched): void {
                $eventDispatched = true;
            }
        );

        $this->service->resendDoubleOptInMail($customer, $context);

        static::assertFalse($eventDispatched);
    }

    public function testResendDoubleOptInMailSkipsWhenWithinCooldown(): void
    {
        $customer = $this->createCustomerEntity('testhash', false);
        $customer->setDoubleOptInEmailSentDate(new \DateTimeImmutable('-1 hour'));
        $context = Generator::generateSalesChannelContext();

        $this->systemConfigService->method('getInt')->willReturn(86400);

        $eventDispatched = false;
        $this->eventDispatcher->addListener(
            CustomerDoubleOptInRegistrationEvent::class,
            static function () use (&$eventDispatched): void {
                $eventDispatched = true;
            }
        );

        $this->service->resendDoubleOptInMail($customer, $context);

        static::assertFalse($eventDispatched);
    }

    public function testMapCustomerDoubleOptInDataReturnUnchangedWhenDoubleOptInDisabled(): void
    {
        $context = Generator::generateSalesChannelContext();
        $input = ['guest' => false, 'email' => 'test@example.com'];

        $this->systemConfigService->method('getBool')->willReturn(false);

        $result = $this->service->mapCustomerDoubleOptInData($input, $context);

        static::assertSame($input, $result);
    }

    public function testMapCustomerDoubleOptInDataSetsFieldsForRegistration(): void
    {
        $context = Generator::generateSalesChannelContext();
        $input = ['guest' => false, 'email' => 'test@example.com'];

        $this->systemConfigService
            ->expects($this->once())
            ->method('getBool')
            ->with('core.loginRegistration.doubleOptInRegistration', $context->getSalesChannelId())
            ->willReturn(true);

        $result = $this->service->mapCustomerDoubleOptInData($input, $context);

        static::assertTrue($result['doubleOptInRegistration']);
        static::assertInstanceOf(\DateTimeImmutable::class, $result['doubleOptInEmailSentDate']);
        static::assertIsString($result['hash']);
        static::assertTrue(Uuid::isValid($result['hash']));
    }

    public function testMapCustomerDoubleOptInDataSetsFieldsForGuestOrder(): void
    {
        $context = Generator::generateSalesChannelContext();
        $input = ['guest' => true, 'email' => 'guest@example.com'];

        $this->systemConfigService
            ->expects($this->once())
            ->method('getBool')
            ->with('core.loginRegistration.doubleOptInGuestOrder', $context->getSalesChannelId())
            ->willReturn(true);

        $result = $this->service->mapCustomerDoubleOptInData($input, $context);

        static::assertTrue($result['doubleOptInRegistration']);
        static::assertInstanceOf(\DateTimeImmutable::class, $result['doubleOptInEmailSentDate']);
        static::assertIsString($result['hash']);
        static::assertTrue(Uuid::isValid($result['hash']));
    }

    private function createCustomerEntity(string $hash, bool $guest): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setHash($hash);
        $customer->setGuest($guest);
        $customer->setEmail('test@example.com');

        return $customer;
    }
}
