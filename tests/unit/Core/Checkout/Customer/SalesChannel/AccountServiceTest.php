<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundByIdException;
use Shopware\Core\Checkout\Customer\Exception\CustomerOptinNotCompletedException;
use Shopware\Core\Checkout\Customer\Exception\PasswordPoliciesUpdatedException;
use Shopware\Core\Checkout\Customer\Password\LegacyPasswordVerifier;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractSwitchDefaultAddressRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\Service\DoubleOptInService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\PasswordHasher\AbstractPasswordHasher;
use Shopware\Core\Framework\PasswordHasher\NativePasswordHasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\SalesChannel\Context\CartRestorer;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AccountService::class)]
class AccountServiceTest extends TestCase
{
    public function testLoginByValidCredentials(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $customer = $salesChannelContext->getCustomer();
        static::assertNotNull($customer);
        $customer->setActive(true);
        $customer->setGuest(false);
        $customer->setPassword(TestDefaults::HASHED_PASSWORD);
        $customer->setEmail('foo@bar.de');
        $customer->setDoubleOptInRegistration(false);

        $customerRepository = new StaticEntityRepository([
            new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                1,
                new CustomerCollection([$customer]),
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            ),
        ]);

        $loggedinSalesChannelContext = Generator::generateSalesChannelContext();
        $cartRestorer = $this->createMock(CartRestorer::class);
        $cartRestorer->expects($this->once())
            ->method('restore')
            ->willReturn($loggedinSalesChannelContext);

        $beforeLoginEventCalled = false;
        $loginEventCalled = false;

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            CustomerBeforeLoginEvent::class,
            static function (CustomerBeforeLoginEvent $event) use ($salesChannelContext, &$beforeLoginEventCalled): void {
                $beforeLoginEventCalled = true;
                static::assertSame('foo@bar.de', $event->getEmail());
                static::assertSame($salesChannelContext, $event->getSalesChannelContext());
            },
        );

        $eventDispatcher->addListener(
            CustomerLoginEvent::class,
            static function (CustomerLoginEvent $event) use ($customer, $loggedinSalesChannelContext, &$loginEventCalled): void {
                $loginEventCalled = true;
                static::assertSame($customer, $event->getCustomer());
                static::assertSame($loggedinSalesChannelContext, $event->getSalesChannelContext());
                static::assertSame($loggedinSalesChannelContext->getToken(), $event->getContextToken());
            },
        );

        $accountService = new AccountService(
            $customerRepository,
            $eventDispatcher,
            static::createStub(LegacyPasswordVerifier::class),
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            $cartRestorer,
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
            new NativePasswordHasher(options: ['cost' => 10]),
            new NullLogger(),
            static::createStub(Connection::class),
        );

        $token = $accountService->loginByCredentials('foo@bar.de', 'shopware', $salesChannelContext);
        static::assertSame($loggedinSalesChannelContext->getToken(), $token);
        static::assertTrue($beforeLoginEventCalled);
        static::assertTrue($loginEventCalled);
        static::assertCount(1, $customerRepository->updates);
        static::assertCount(1, $customerRepository->updates[0]);
        static::assertIsArray($customerRepository->updates[0][0]);
        static::assertCount(2, $customerRepository->updates[0][0]);
        static::assertSame($customer->getId(), $customerRepository->updates[0][0]['id']);
        static::assertInstanceOf(\DateTimeImmutable::class, $customerRepository->updates[0][0]['lastLogin']);
    }

    public function testLoginFailsByInvalidCredentials(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $customer = $salesChannelContext->getCustomer();
        static::assertNotNull($customer);
        $customer->setActive(true);
        $customer->setGuest(false);
        $customer->setPassword(TestDefaults::HASHED_PASSWORD);
        $customer->setEmail('foo@bar.de');
        $customer->setDoubleOptInRegistration(false);

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                1,
                new CustomerCollection([$customer]),
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            ));

        $cartRestorer = $this->createMock(CartRestorer::class);
        $cartRestorer->expects($this->never())
            ->method('restore');

        $accountService = new AccountService(
            $customerRepository,
            new EventDispatcher(),
            static::createStub(LegacyPasswordVerifier::class),
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            $cartRestorer,
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
            new NativePasswordHasher(options: ['cost' => 10]),
            new NullLogger(),
            static::createStub(Connection::class),
        );

        $this->expectException(BadCredentialsException::class);
        $accountService->loginByCredentials('foo@bar.de', 'invalidPassword', $salesChannelContext);
    }

    public function testGetCustomerByLoginRehashesOutdatedPasswordHash(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $customer = $this->createLoginableCustomer($salesChannelContext);
        $customerRepository = $this->createCustomerRepository($customer, $salesChannelContext);

        $passwordUpdates = [];
        $connection = static::createStub(Connection::class);
        $connection->method('update')->willReturnCallback(
            /**
             * @param array<string, mixed> $data
             * @param array<string, mixed> $criteria
             */
            static function (string $table, array $data, array $criteria) use (&$passwordUpdates): int {
                static::assertSame('customer', $table);
                $passwordUpdates[] = ['id' => $criteria['id'], 'password' => $data['password']];

                return 1;
            }
        );

        // TestDefaults::HASHED_PASSWORD uses cost 10
        $accountService = $this->createAccountServiceForLogin($customerRepository, new NativePasswordHasher(options: ['cost' => 11]), $connection);

        $accountService->getCustomerByLogin('foo@bar.de', 'shopware', $salesChannelContext);

        static::assertCount(1, $passwordUpdates);
        static::assertSame(Uuid::fromHexToBytes($customer->getId()), $passwordUpdates[0]['id']);
        static::assertIsString($passwordUpdates[0]['password']);
        static::assertStringStartsWith('$2y$11$', $passwordUpdates[0]['password']);
        static::assertTrue(password_verify('shopware', $passwordUpdates[0]['password']));
        static::assertSame($passwordUpdates[0]['password'], $customer->getPassword());
        static::assertSame([], $customerRepository->updates, 'Rehashing must not dispatch a customer written event, which would revoke other sessions');
    }

    public function testGetCustomerByLoginKeepsUpToDatePasswordHash(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $customer = $this->createLoginableCustomer($salesChannelContext);
        $customerRepository = $this->createCustomerRepository($customer, $salesChannelContext);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('update');

        $accountService = $this->createAccountServiceForLogin($customerRepository, new NativePasswordHasher(options: ['cost' => 10]), $connection);

        $accountService->getCustomerByLogin('foo@bar.de', 'shopware', $salesChannelContext);

        static::assertSame(TestDefaults::HASHED_PASSWORD, $customer->getPassword());
    }

    public function testGetCustomerByLoginDoesNotRehashForUnconfirmedDoubleOptIn(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $customer = $this->createLoginableCustomer($salesChannelContext);
        $customer->setDoubleOptInRegistration(true);
        $customerRepository = $this->createCustomerRepository($customer, $salesChannelContext);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('update');

        // TestDefaults::HASHED_PASSWORD uses cost 10
        $accountService = $this->createAccountServiceForLogin($customerRepository, new NativePasswordHasher(options: ['cost' => 11]), $connection);

        try {
            $accountService->getCustomerByLogin('foo@bar.de', 'shopware', $salesChannelContext);
            static::fail('Expected the login of an unconfirmed customer to fail');
        } catch (CustomerOptinNotCompletedException) {
        }

        static::assertSame(TestDefaults::HASHED_PASSWORD, $customer->getPassword());
    }

    public function testGetCustomerByLoginSucceedsWhenRehashingFails(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $customer = $this->createLoginableCustomer($salesChannelContext);
        $customerRepository = $this->createCustomerRepository($customer, $salesChannelContext);

        $connection = static::createStub(Connection::class);
        $connection->method('update')->willThrowException(new \RuntimeException('database gone'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $accountService = $this->createAccountServiceForLogin($customerRepository, new NativePasswordHasher(options: ['cost' => 11]), $connection, $logger);

        static::assertSame($customer, $accountService->getCustomerByLogin('foo@bar.de', 'shopware', $salesChannelContext));
        static::assertSame(TestDefaults::HASHED_PASSWORD, $customer->getPassword());
    }

    public function testGetCustomerByLoginThrowsBadCredentialsWhenEmailNotFound(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                0,
                new CustomerCollection(),
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            ));

        $passwordHasher = $this->createMock(AbstractPasswordHasher::class);
        $passwordHasher->expects($this->once())->method('verifyPlaceholder')->with('any-password');

        $accountService = new AccountService(
            $customerRepository,
            new EventDispatcher(),
            static::createStub(LegacyPasswordVerifier::class),
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
            $passwordHasher,
            new NullLogger(),
            static::createStub(Connection::class),
        );

        $this->expectException(BadCredentialsException::class);
        $accountService->getCustomerByLogin('unknown@example.com', 'any-password', $salesChannelContext);
    }

    public function testGetCustomerByIdThrowsPasswordPoliciesChangedException(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $customer = $salesChannelContext->getCustomer();
        static::assertNotNull($customer);
        $customer->setActive(true);
        $customer->setGuest(false);
        $customer->setLegacyPassword('foo');
        $customer->setLegacyEncoder('bar');

        $legacyPasswordVerifier = $this->createMock(LegacyPasswordVerifier::class);
        $legacyPasswordVerifier->expects($this->once())
            ->method('verify')
            ->with('password', $customer)
            ->willReturn(true);

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                1,
                new CustomerCollection([$customer]),
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            ));

        $exception = new WriteConstraintViolationException(new ConstraintViolationList([new ConstraintViolation('', '', [], '', '/password', '')]), '/');
        $writeException = new WriteException();
        $writeException->add($exception);

        $customerRepository->expects($this->once())
            ->method('update')
            ->with([[
                'id' => $customer->getId(),
                'password' => 'password',
                'legacyPassword' => null,
                'legacyEncoder' => null,
            ]], $salesChannelContext->getContext())
            ->willThrowException($writeException);

        $accountService = new AccountService(
            $customerRepository,
            static::createStub(EventDispatcherInterface::class),
            $legacyPasswordVerifier,
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
            new NativePasswordHasher(options: ['cost' => 10]),
            new NullLogger(),
            static::createStub(Connection::class),
        );

        $this->expectExceptionObject(new PasswordPoliciesUpdatedException());
        $accountService->getCustomerByLogin('user', 'password', $salesChannelContext);
    }

    public function testGetCustomerByIdIgnoresOtherWriteViolations(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $customer = $salesChannelContext->getCustomer();
        static::assertNotNull($customer);
        $customer->setActive(true);
        $customer->setGuest(false);
        $customer->setLegacyPassword('foo');
        $customer->setLegacyEncoder('bar');

        $legacyPasswordVerifier = $this->createMock(LegacyPasswordVerifier::class);
        $legacyPasswordVerifier->expects($this->once())
            ->method('verify')
            ->with('password', $customer)
            ->willReturn(true);

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                1,
                new CustomerCollection([$customer]),
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            ));

        $exception = CustomerException::badCredentials();
        $writeException = new WriteException();
        $writeException->add($exception);

        $customerRepository->expects($this->once())
            ->method('update')
            ->with([[
                'id' => $customer->getId(),
                'password' => 'password',
                'legacyPassword' => null,
                'legacyEncoder' => null,
            ]], $salesChannelContext->getContext())
            ->willThrowException($writeException);

        $accountService = new AccountService(
            $customerRepository,
            static::createStub(EventDispatcherInterface::class),
            $legacyPasswordVerifier,
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
            new NativePasswordHasher(options: ['cost' => 10]),
            new NullLogger(),
            static::createStub(Connection::class),
        );

        $this->expectException(WriteException::class);
        $accountService->getCustomerByLogin('user', 'password', $salesChannelContext);
    }

    public function testSetDefaultBillingAddress(): void
    {
        $context = Generator::generateSalesChannelContext();
        $customer = $context->getCustomer();

        static::assertNotNull($customer);

        $switcher = $this->createMock(AbstractSwitchDefaultAddressRoute::class);
        $switcher
            ->expects($this->once())
            ->method('swap')
            ->with('billing-address-id', AbstractSwitchDefaultAddressRoute::TYPE_BILLING, $context, $customer);

        $accountService = new AccountService(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(LegacyPasswordVerifier::class),
            $switcher,
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
            new NativePasswordHasher(options: ['cost' => 10]),
            new NullLogger(),
            static::createStub(Connection::class),
        );

        $accountService->setDefaultBillingAddress('billing-address-id', $context, $customer);
    }

    public function testSetDefaultShippingAddress(): void
    {
        $context = Generator::generateSalesChannelContext();
        $customer = $context->getCustomer();

        static::assertNotNull($customer);

        $switcher = $this->createMock(AbstractSwitchDefaultAddressRoute::class);
        $switcher
            ->expects($this->once())
            ->method('swap')
            ->with('shipping-address-id', AbstractSwitchDefaultAddressRoute::TYPE_SHIPPING, $context, $customer);

        $accountService = new AccountService(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(LegacyPasswordVerifier::class),
            $switcher,
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
            new NativePasswordHasher(options: ['cost' => 10]),
            new NullLogger(),
            static::createStub(Connection::class),
        );

        $accountService->setDefaultShippingAddress('shipping-address-id', $context, $customer);
    }

    public function testLoginById(): void
    {
        $context = Generator::generateSalesChannelContext();

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setActive(true);
        $customer->setBoundSalesChannelId($context->getSalesChannel()->getId());
        $customer->setEmail('foo@bar.de');

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                1,
                new CustomerCollection([$customer]),
                null,
                new Criteria(),
                $context->getContext()
            ));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->with(static::callback(static function ($event) use ($context, $customer): bool {
                if ($event instanceof CustomerBeforeLoginEvent) {
                    static::assertSame($context, $event->getSalesChannelContext());
                    static::assertSame($customer->getEmail(), $event->getEmail());

                    return true;
                }

                if ($event instanceof CustomerLoginEvent) {
                    static::assertSame($customer, $event->getCustomer());

                    return true;
                }

                return false;
            }));

        $accountService = new AccountService(
            $repo,
            $dispatcher,
            static::createStub(LegacyPasswordVerifier::class),
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
            new NativePasswordHasher(options: ['cost' => 10]),
            new NullLogger(),
            static::createStub(Connection::class),
        );

        $accountService->loginById($customer->getId(), $context);
    }

    public function testLoginByIdWithNonValidId(): void
    {
        $context = Generator::generateSalesChannelContext();

        $accountService = new AccountService(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(LegacyPasswordVerifier::class),
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
            new NativePasswordHasher(options: ['cost' => 10]),
            new NullLogger(),
            static::createStub(Connection::class),
        );

        $this->expectException(BadCredentialsException::class);

        $accountService->loginById('foo', $context);
    }

    public function testLoginByIdNotFound(): void
    {
        $context = Generator::generateSalesChannelContext();

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                0,
                new CustomerCollection(),
                null,
                new Criteria(),
                $context->getContext()
            ));

        $accountService = new AccountService(
            $repo,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(LegacyPasswordVerifier::class),
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
            new NativePasswordHasher(options: ['cost' => 10]),
            new NullLogger(),
            static::createStub(Connection::class),
        );

        $this->expectException(CustomerNotFoundByIdException::class);

        $accountService->loginById(Uuid::randomHex(), $context);
    }

    public function testPasswordTooLongThrowsBadCredentials(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $accountService = new AccountService(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(LegacyPasswordVerifier::class),
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
            new NativePasswordHasher(options: ['cost' => 10]),
            new NullLogger(),
            static::createStub(Connection::class),
        );

        static::expectException(BadCredentialsException::class);

        $accountService->loginByCredentials('foo@bar.de', \str_repeat('a', PasswordHasherInterface::MAX_PASSWORD_LENGTH + 1), $salesChannelContext);
    }

    public function testGetCustomerByLoginWithUnconfirmedDoubleOptIn(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $customer = $salesChannelContext->getCustomer();
        static::assertNotNull($customer);
        $customer->setActive(true);
        $customer->setGuest(false);
        $customer->setPassword(TestDefaults::HASHED_PASSWORD);
        $customer->setEmail('foo@bar.de');
        $customer->setDoubleOptInRegistration(true);

        $customerRepository = new StaticEntityRepository([
            new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                1,
                new CustomerCollection([$customer]),
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            ),
        ]);

        $doubleOptInService = $this->createMock(DoubleOptInService::class);
        $doubleOptInService->expects($this->once())
            ->method('resendDoubleOptInMail')
            ->with($customer, $salesChannelContext);

        $accountService = new AccountService(
            $customerRepository,
            new EventDispatcher(),
            static::createStub(LegacyPasswordVerifier::class),
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            static::createStub(CartRestorer::class),
            $doubleOptInService,
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
            new NativePasswordHasher(options: ['cost' => 10]),
            new NullLogger(),
            static::createStub(Connection::class),
        );

        $this->expectException(CustomerOptinNotCompletedException::class);
        $accountService->getCustomerByLogin('foo@bar.de', 'shopware', $salesChannelContext);
    }

    private function createLoginableCustomer(SalesChannelContext $salesChannelContext): CustomerEntity
    {
        $customer = $salesChannelContext->getCustomer();
        static::assertNotNull($customer);
        $customer->setActive(true);
        $customer->setGuest(false);
        $customer->setPassword(TestDefaults::HASHED_PASSWORD);
        $customer->setEmail('foo@bar.de');
        $customer->setDoubleOptInRegistration(false);

        return $customer;
    }

    /**
     * @return StaticEntityRepository<CustomerCollection>
     */
    private function createCustomerRepository(CustomerEntity $customer, SalesChannelContext $salesChannelContext): StaticEntityRepository
    {
        return new StaticEntityRepository([
            new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                1,
                new CustomerCollection([$customer]),
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            ),
        ]);
    }

    /**
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    private function createAccountServiceForLogin(
        EntityRepository $customerRepository,
        AbstractPasswordHasher $passwordHasher,
        Connection $connection,
        LoggerInterface $logger = new NullLogger(),
    ): AccountService {
        return new AccountService(
            $customerRepository,
            new EventDispatcher(),
            static::createStub(LegacyPasswordVerifier::class),
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
            $passwordHasher,
            $logger,
            $connection,
        );
    }
}
