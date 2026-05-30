<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopware\Core\Checkout\Customer\Event\DoubleOptInGuestOrderEvent;
use Shopware\Core\Checkout\Customer\Service\DoubleOptInService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DoubleOptInService::class)]
class DoubleOptInServiceTest extends TestCase
{
    private EventDispatcher $eventDispatcher;

    /**
     * @var StaticEntityRepository<CustomerCollection>
     */
    private StaticEntityRepository $customerRepository;

    /**
     * @var StaticEntityRepository<SalesChannelDomainCollection>
     */
    private StaticEntityRepository $salesChannelDomainRepository;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $this->customerRepository = new StaticEntityRepository([]);
        $this->salesChannelDomainRepository = new StaticEntityRepository([]);
    }

    public function testSendDoubleOptInMailDispatchesRegistrationEvent(): void
    {
        $customer = $this->createCustomerEntity('testhash', false);
        $context = Generator::generateSalesChannelContext();

        $dispatched = null;
        $this->eventDispatcher->addListener(
            CustomerDoubleOptInRegistrationEvent::class,
            static function (CustomerDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService()->sendDoubleOptInMail($customer, $context, 'https://shop.example.com');

        static::assertInstanceOf(CustomerDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringStartsWith('https://shop.example.com', $dispatched->getConfirmUrl());
    }

    public function testSendDoubleOptInMailDispatchesGuestEvent(): void
    {
        $customer = $this->createCustomerEntity('testhash', true);
        $context = Generator::generateSalesChannelContext();

        $dispatched = null;
        $this->eventDispatcher->addListener(
            DoubleOptInGuestOrderEvent::class,
            static function (DoubleOptInGuestOrderEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService()->sendDoubleOptInMail($customer, $context, 'https://shop.example.com');

        static::assertInstanceOf(DoubleOptInGuestOrderEvent::class, $dispatched);
    }

    public function testSendDoubleOptInMailWithRedirectTo(): void
    {
        $customer = $this->createCustomerEntity('testhash', false);
        $context = Generator::generateSalesChannelContext();

        $dispatched = null;
        $this->eventDispatcher->addListener(
            CustomerDoubleOptInRegistrationEvent::class,
            static function (CustomerDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService()->sendDoubleOptInMail($customer, $context, 'https://shop.example.com', 'account');

        static::assertInstanceOf(CustomerDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringContainsString('redirectTo=account', $dispatched->getConfirmUrl());
    }

    public function testSendDoubleOptInMailWithRedirectToAndParameters(): void
    {
        $customer = $this->createCustomerEntity('testhash', false);
        $context = Generator::generateSalesChannelContext();

        $dispatched = null;
        $this->eventDispatcher->addListener(
            CustomerDoubleOptInRegistrationEvent::class,
            static function (CustomerDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService()->sendDoubleOptInMail(
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

        $dispatched = null;
        $this->eventDispatcher->addListener(
            CustomerDoubleOptInRegistrationEvent::class,
            static function (CustomerDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService([
            'core.loginRegistration.confirmationUrl' => '/custom/confirm?em=%%HASHEDEMAIL%%&hash=%%SUBSCRIBEHASH%%',
        ])->sendDoubleOptInMail($customer, $context, 'https://shop.example.com');

        static::assertInstanceOf(CustomerDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringContainsString('/custom/confirm', $dispatched->getConfirmUrl());
        static::assertStringContainsString('customhash', $dispatched->getConfirmUrl());
    }

    public function testResendDoubleOptInMailDisabledWhenIntervalIsZero(): void
    {
        $customer = $this->createCustomerEntity('testhash', false);
        $customer->setDoubleOptInEmailSentDate(new \DateTimeImmutable('-10 days'));
        $context = Generator::generateSalesChannelContext();

        $eventDispatched = false;
        $this->eventDispatcher->addListener(
            CustomerDoubleOptInRegistrationEvent::class,
            static function () use (&$eventDispatched): void {
                $eventDispatched = true;
            }
        );

        $this->createService([
            'core.loginRegistration.doubleOptInResendInterval' => 0,
        ])->resendDoubleOptInMail($customer, $context);

        static::assertFalse($eventDispatched);
        static::assertEmpty($this->customerRepository->updates);
    }

    public function testResendDoubleOptInMailDisabledWhenIntervalNotConfigured(): void
    {
        $customer = $this->createCustomerEntity('testhash', false);
        $customer->setDoubleOptInEmailSentDate(new \DateTimeImmutable('-10 days'));
        $context = Generator::generateSalesChannelContext();

        $eventDispatched = false;
        $this->eventDispatcher->addListener(
            CustomerDoubleOptInRegistrationEvent::class,
            static function () use (&$eventDispatched): void {
                $eventDispatched = true;
            }
        );

        $this->createService()->resendDoubleOptInMail($customer, $context);

        static::assertFalse($eventDispatched);
        static::assertEmpty($this->customerRepository->updates);
    }

    public function testResendDoubleOptInMailSkipsWhenNoSentDate(): void
    {
        $customer = $this->createCustomerEntity('testhash', false);
        // no doubleOptInEmailSentDate set
        $context = Generator::generateSalesChannelContext();

        $eventDispatched = false;
        $this->eventDispatcher->addListener(
            CustomerDoubleOptInRegistrationEvent::class,
            static function () use (&$eventDispatched): void {
                $eventDispatched = true;
            }
        );

        $this->createService([
            'core.loginRegistration.doubleOptInResendInterval' => 24,
        ])->resendDoubleOptInMail($customer, $context);

        static::assertFalse($eventDispatched);
    }

    public function testResendDoubleOptInMailSkipsWhenWithinCooldown(): void
    {
        $customer = $this->createCustomerEntity('testhash', false);
        $customer->setDoubleOptInEmailSentDate(new \DateTimeImmutable('-1 hour'));
        $context = Generator::generateSalesChannelContext();

        $eventDispatched = false;
        $this->eventDispatcher->addListener(
            CustomerDoubleOptInRegistrationEvent::class,
            static function () use (&$eventDispatched): void {
                $eventDispatched = true;
            }
        );

        $this->createService([
            'core.loginRegistration.doubleOptInResendInterval' => 24,
        ])->resendDoubleOptInMail($customer, $context);

        static::assertFalse($eventDispatched);
    }

    public function testResendDoubleOptInMailSendsWhenCooldownElapsed(): void
    {
        $customer = $this->createCustomerEntity('testhash', false);
        $customer->setDoubleOptInEmailSentDate(new \DateTimeImmutable('-2 days'));
        $context = Generator::generateSalesChannelContext();

        $dispatched = null;
        $this->eventDispatcher->addListener(
            CustomerDoubleOptInRegistrationEvent::class,
            static function (CustomerDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService([
            'core.loginRegistration.doubleOptInResendInterval' => 24,
            'core.loginRegistration.doubleOptInDomain' => 'https://shop.example.com',
        ])->resendDoubleOptInMail($customer, $context);

        static::assertInstanceOf(CustomerDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertCount(1, $this->customerRepository->updates);
        static::assertSame($customer->getId(), $this->customerRepository->updates[0][0]['id']);
        static::assertInstanceOf(\DateTimeImmutable::class, $this->customerRepository->updates[0][0]['doubleOptInEmailSentDate']);
    }

    public function testResolveDomainUrlUsesDomainFromContextDomainId(): void
    {
        $domainId = Uuid::randomHex();
        $domain = new SalesChannelDomainEntity();
        $domain->setId($domainId);
        $domain->setUrl('https://domain-by-id.example.com');
        $domain->setLanguageId(Uuid::randomHex());

        $salesChannel = $this->createSalesChannelWithDomains([$domain]);

        $customer = $this->createCustomerEntity('testhash', false, Uuid::randomHex());
        $customer->setDoubleOptInEmailSentDate(new \DateTimeImmutable('-2 days'));
        $context = Generator::generateSalesChannelContext(domainId: $domainId, salesChannel: $salesChannel);

        $dispatched = null;
        $this->eventDispatcher->addListener(
            CustomerDoubleOptInRegistrationEvent::class,
            static function (CustomerDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService([
            'core.loginRegistration.doubleOptInResendInterval' => 24,
        ])->resendDoubleOptInMail($customer, $context);

        static::assertInstanceOf(CustomerDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringStartsWith('https://domain-by-id.example.com', $dispatched->getConfirmUrl());
    }

    public function testResolveDomainUrlUsesDomainMatchingLanguageId(): void
    {
        $languageId = Uuid::randomHex();

        $matchingDomain = new SalesChannelDomainEntity();
        $matchingDomain->setId(Uuid::randomHex());
        $matchingDomain->setUrl('https://lang-domain.example.com');
        $matchingDomain->setLanguageId($languageId);

        $otherDomain = new SalesChannelDomainEntity();
        $otherDomain->setId(Uuid::randomHex());
        $otherDomain->setUrl('https://other-domain.example.com');
        $otherDomain->setLanguageId(Uuid::randomHex());

        $salesChannel = $this->createSalesChannelWithDomains([$matchingDomain, $otherDomain]);

        $customer = $this->createCustomerEntity('testhash', false, $languageId);
        $customer->setDoubleOptInEmailSentDate(new \DateTimeImmutable('-2 days'));
        // Pass domainId: null so the domainId path is not taken
        $context = Generator::generateSalesChannelContext(domainId: null, salesChannel: $salesChannel);

        $dispatched = null;
        $this->eventDispatcher->addListener(
            CustomerDoubleOptInRegistrationEvent::class,
            static function (CustomerDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService([
            'core.loginRegistration.doubleOptInResendInterval' => 24,
        ])->resendDoubleOptInMail($customer, $context);

        static::assertInstanceOf(CustomerDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringStartsWith('https://lang-domain.example.com', $dispatched->getConfirmUrl());
    }

    public function testResolveDomainUrlFallsBackToRepositoryWhenDomainsNoneMatch(): void
    {
        $collectionDomain = new SalesChannelDomainEntity();
        $collectionDomain->setId(Uuid::randomHex());
        $collectionDomain->setUrl('https://collection-domain.example.com');
        $collectionDomain->setLanguageId(Uuid::randomHex());

        $salesChannel = $this->createSalesChannelWithDomains([$collectionDomain]);

        // Customer language and context domainId both do not match any domain in the collection
        $customer = $this->createCustomerEntity('testhash', false, Uuid::randomHex());
        $customer->setDoubleOptInEmailSentDate(new \DateTimeImmutable('-2 days'));
        $context = Generator::generateSalesChannelContext(domainId: null, salesChannel: $salesChannel);

        $repoDomain = new SalesChannelDomainEntity();
        $repoDomain->setId(Uuid::randomHex());
        $repoDomain->setUrl('https://repo-fallback.example.com');

        $this->salesChannelDomainRepository = new StaticEntityRepository([
            new SalesChannelDomainCollection([$repoDomain]),
        ]);

        $dispatched = null;
        $this->eventDispatcher->addListener(
            CustomerDoubleOptInRegistrationEvent::class,
            static function (CustomerDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService([
            'core.loginRegistration.doubleOptInResendInterval' => 24,
        ])->resendDoubleOptInMail($customer, $context);

        static::assertInstanceOf(CustomerDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringStartsWith('https://repo-fallback.example.com', $dispatched->getConfirmUrl());
    }

    public function testResendDoubleOptInMailFallsBackToSalesChannelDomainRepository(): void
    {
        $customer = $this->createCustomerEntity('testhash', false);
        $customer->setDoubleOptInEmailSentDate(new \DateTimeImmutable('-2 days'));
        $context = Generator::generateSalesChannelContext();

        $domain = new SalesChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl('https://fallback-domain.example.com');

        $this->salesChannelDomainRepository = new StaticEntityRepository([
            new SalesChannelDomainCollection([$domain]),
        ]);

        $dispatched = null;
        $this->eventDispatcher->addListener(
            CustomerDoubleOptInRegistrationEvent::class,
            static function (CustomerDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService([
            'core.loginRegistration.doubleOptInResendInterval' => 24,
        ])->resendDoubleOptInMail($customer, $context);

        static::assertInstanceOf(CustomerDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringStartsWith('https://fallback-domain.example.com', $dispatched->getConfirmUrl());
    }

    public function testMapCustomerDoubleOptInDataReturnUnchangedWhenDoubleOptInDisabled(): void
    {
        $context = Generator::generateSalesChannelContext();
        $input = ['guest' => false, 'email' => 'test@example.com'];

        $result = $this->createService([
            'core.loginRegistration.doubleOptInRegistration' => false,
        ])->mapCustomerDoubleOptInData($input, $context);

        static::assertSame($input, $result);
    }

    public function testMapCustomerDoubleOptInDataSetsFieldsForRegistration(): void
    {
        $context = Generator::generateSalesChannelContext();
        $input = ['guest' => false, 'email' => 'test@example.com'];

        $result = $this->createService([
            'core.loginRegistration.doubleOptInRegistration' => true,
        ])->mapCustomerDoubleOptInData($input, $context);

        static::assertTrue($result['doubleOptInRegistration']);
        static::assertInstanceOf(\DateTimeImmutable::class, $result['doubleOptInEmailSentDate']);
        static::assertIsString($result['hash']);
        static::assertTrue(Uuid::isValid($result['hash']));
    }

    public function testMapCustomerDoubleOptInDataSetsFieldsForGuestOrder(): void
    {
        $context = Generator::generateSalesChannelContext();
        $input = ['guest' => true, 'email' => 'guest@example.com'];

        $result = $this->createService([
            'core.loginRegistration.doubleOptInGuestOrder' => true,
        ])->mapCustomerDoubleOptInData($input, $context);

        static::assertTrue($result['doubleOptInRegistration']);
        static::assertInstanceOf(\DateTimeImmutable::class, $result['doubleOptInEmailSentDate']);
        static::assertIsString($result['hash']);
        static::assertTrue(Uuid::isValid($result['hash']));
    }

    /**
     * @param array<string, mixed> $systemConfig
     */
    private function createService(array $systemConfig = []): DoubleOptInService
    {
        return new DoubleOptInService(
            $this->customerRepository,
            $this->eventDispatcher,
            new StaticSystemConfigService($systemConfig),
            $this->salesChannelDomainRepository,
            new NativeClock(),
        );
    }

    private function createCustomerEntity(string $hash, bool $guest, ?string $languageId = null): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setHash($hash);
        $customer->setGuest($guest);
        $customer->setEmail('test@example.com');
        $customer->setLanguageId($languageId ?? Defaults::LANGUAGE_SYSTEM);

        return $customer;
    }

    /**
     * @param SalesChannelDomainEntity[] $domains
     */
    private function createSalesChannelWithDomains(array $domains): SalesChannelEntity
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(TestDefaults::SALES_CHANNEL);
        $salesChannel->setNavigationCategoryId(Generator::NAVIGATION_CATEGORY);
        $salesChannel->setTaxCalculationType(Generator::TAX_CALCULATION_TYPE);
        $salesChannel->setNavigationCategoryDepth(2);
        $salesChannel->setDomains(new SalesChannelDomainCollection($domains));

        return $salesChannel;
    }
}
