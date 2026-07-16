<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegistrationReplayedEvent;
use Shopware\Core\Checkout\Customer\Event\GuestCustomerRegisterEvent;
use Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute;
use Shopware\Core\Checkout\Customer\Service\DoubleOptInService;
use Shopware\Core\Checkout\Customer\Service\RegistrationIdempotencyGuard;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentification;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerZipCode;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiCustomFieldMapper;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticSalesChannelRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(RegisterRoute::class)]
class RegisterRouteTest extends TestCase
{
    private const REPLAYED_CUSTOMER_ID = 'b48b125a1a2e4a4c8ee9d64b1a15e0a1';

    private const WINNER_CONTEXT_TOKEN = 'winner-context-token';

    public function testAccountType(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.loginRegistration.showAccountTypeSelection' => true,
                'core.loginRegistration.passwordMinLength' => '8',
            ],
            'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel' => true,
        ]);

        $result = static::createStub(EntitySearchResult::class);
        $customerEntity = new CustomerEntity();
        $customerEntity->setDoubleOptInRegistration(false);
        $customerEntity->setId('customer-1');
        $customerEntity->setGuest(false);
        $result->method('getEntities')->willReturn(new CustomerCollection([$customerEntity]));

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->method('search')->willReturn($result);
        $customerRepository
            ->expects($this->once())
            ->method('create')
            ->willReturnCallback(static function (array $create) {
                static::assertCount(1, $create);
                static::assertArrayHasKey('accountType', $create[0]);
                static::assertSame(CustomerEntity::ACCOUNT_TYPE_PRIVATE, $create[0]['accountType']);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
            });

        $register = $this->createRegisterRoute(
            systemConfigService: $systemConfigService,
            customerRepository: $customerRepository
        );

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'countryId' => Uuid::randomHex(),
            ],
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'shippingAddress' => [
                'countryId' => Uuid::randomHex(),
            ],
        ];

        $salesChannelContext = Generator::generateSalesChannelContext();

        $register->register(new RequestDataBag($data), $salesChannelContext, false);
    }

    public function testValidateShippingAddressWithBusinessAccount(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.loginRegistration.showAccountTypeSelection' => true,
                'core.loginRegistration.passwordMinLength' => '8',
            ],
            'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel' => true,
        ]);

        $customerEntity = new CustomerEntity();
        $customerEntity->setDoubleOptInRegistration(false);
        $customerEntity->setId('customer-1');
        $customerEntity->setGuest(false);

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository(
            [new CustomerCollection([$customerEntity])],
            new CustomerDefinition()
        );

        $definition = new DataValidationDefinition('address.create');

        $addressValidation = static::createStub(DataValidationFactoryInterface::class);
        $addressValidation->method('create')->willReturn($definition);

        $dispatcher = static::createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(static function (Event $event) use ($definition) {
            if ($event instanceof BuildValidationEvent && $event->getName() === 'framework.validation.address.create') {
                $definition->add('company', new NotBlank());
                $definition->set('zipcode', new CustomerZipCode(countryId: '123'));

                static::assertSame($event->getDefinition()->getProperties(), $definition->getProperties());
            }

            return $event;
        });

        $register = $this->createRegisterRoute(
            dataValidator: new DataValidator(Validation::createValidatorBuilder()->getValidator()),
            eventDispatcher: $dispatcher,
            addressValidationFactory: $addressValidation,
            systemConfigService: $systemConfigService,
            customerRepository: $customerRepository
        );

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'id' => Uuid::randomHex(),
            ],
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'shippingAddress' => [
                'id' => Uuid::randomHex(),
                'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            ],
        ];

        $salesChannelContext = Generator::generateSalesChannelContext();

        $register->register(new RequestDataBag($data), $salesChannelContext, false);
    }

    public function testValidateBillingAddressWithBusinessAccount(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.loginRegistration.showAccountTypeSelection' => true,
                'core.loginRegistration.passwordMinLength' => '8',
            ],
            'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel' => true,
        ]);

        $customerEntity = new CustomerEntity();
        $customerEntity->setDoubleOptInRegistration(false);
        $customerEntity->setId('customer-1');
        $customerEntity->setGuest(false);

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository(
            [new CustomerCollection([$customerEntity])],
            new CustomerDefinition()
        );

        $definition = new DataValidationDefinition('address.create');

        $addressValidation = static::createStub(DataValidationFactoryInterface::class);
        $addressValidation->method('create')->willReturn($definition);

        $dispatcher = static::createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(static function (Event $event) {
            if ($event instanceof BuildValidationEvent && $event->getName() === 'framework.validation.address.create') {
                $definition = new DataValidationDefinition('address.create');

                $definition->add('company', new NotBlank());
                $definition->set('zipcode', new CustomerZipCode(countryId: null));
                $definition->add('zipcode', new Length(max: CustomerAddressDefinition::MAX_LENGTH_ZIPCODE));

                static::assertNull($event->getData()->get('shippingAddress'));
                static::assertSame(CustomerEntity::ACCOUNT_TYPE_BUSINESS, $event->getData()->get('accountType'));
                static::assertEquals($definition->getProperties(), $event->getDefinition()->getProperties());
            }

            return $event;
        });

        $register = $this->createRegisterRoute(
            eventDispatcher: $dispatcher,
            addressValidationFactory: $addressValidation,
            systemConfigService: $systemConfigService,
            customerRepository: $customerRepository
        );

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'id' => Uuid::randomHex(),
            ],
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
        ];

        $salesChannelContext = Generator::generateSalesChannelContext();

        $register->register(new RequestDataBag($data), $salesChannelContext, false);
    }

    public function testValidateBillingAddressVatIdsWithBusinessAccountThrowException(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.loginRegistration.showAccountTypeSelection' => true,
                'core.loginRegistration.passwordMinLength' => '8',
            ],
            'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel' => true,
        ]);

        $customerEntity = new CustomerEntity();
        $customerEntity->setDoubleOptInRegistration(false);
        $customerEntity->setId('customer-1');
        $customerEntity->setGuest(false);

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository(
            [new CustomerCollection([$customerEntity])],
            new CustomerDefinition(),
        );

        $definition = new DataValidationDefinition('address.create');

        $addressValidation = static::createStub(DataValidationFactoryInterface::class);
        $addressValidation->method('create')->willReturn($definition);

        $dispatcher = static::createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(static function (Event $event) {
            if ($event instanceof BuildValidationEvent && $event->getName() === 'framework.validation.address.create') {
                $definition = new DataValidationDefinition('address.create');

                $definition->add('company', new NotBlank());
                $definition->set('zipcode', new CustomerZipCode(countryId: '123'));
                $definition->add('zipcode', new Length(max: CustomerAddressDefinition::MAX_LENGTH_ZIPCODE));

                static::assertNull($event->getData()->get('shippingAddress'));
                static::assertSame(CustomerEntity::ACCOUNT_TYPE_BUSINESS, $event->getData()->get('accountType'));
                static::assertEquals($definition->getProperties(), $event->getDefinition()->getProperties());
            }

            return $event;
        });

        $register = $this->createRegisterRoute(
            eventDispatcher: $dispatcher,
            addressValidationFactory: $addressValidation,
            systemConfigService: $systemConfigService,
            customerRepository: $customerRepository
        );

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'id' => Uuid::randomHex(),
                'countryId' => '123',
            ],
            'vatIds' => ['123'],
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
        ];

        $salesChannelContext = Generator::generateSalesChannelContext();

        static::expectException(CustomerException::class);

        $register->register(new RequestDataBag($data), $salesChannelContext, false);
    }

    public function testCustomFields(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.loginRegistration.showAccountTypeSelection' => true,
                'core.loginRegistration.passwordMinLength' => '8',
            ],
            'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel' => true,
        ]);

        $result = static::createStub(EntitySearchResult::class);
        $customerEntity = new CustomerEntity();
        $customerEntity->setDoubleOptInRegistration(false);
        $customerEntity->setId('customer-1');
        $customerEntity->setGuest(false);
        $result->method('getEntities')->willReturn(new CustomerCollection([$customerEntity]));

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->method('search')->willReturn($result);
        $customerRepository
            ->expects($this->once())
            ->method('create')
            ->willReturnCallback(static function (array $create) {
                static::assertSame(['mapped' => 1], $create[0]['customFields']);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
            });

        $customFieldMapper = new StoreApiCustomFieldMapper(static::createStub(Connection::class), [
            CustomerDefinition::ENTITY_NAME => [
                ['name' => 'mapped', 'type' => 'int'],
            ],
        ]);

        $register = $this->createRegisterRoute(
            customFieldMapper: $customFieldMapper,
            systemConfigService: $systemConfigService,
            customerRepository: $customerRepository
        );

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'countryId' => Uuid::randomHex(),
            ],
            'customFields' => [
                'test' => '1',
                'mapped' => '1',
            ],
        ];

        $salesChannelContext = Generator::generateSalesChannelContext();

        $register->register(new RequestDataBag($data), $salesChannelContext, false);
    }

    public function testSalutationIdIsAssignedDefaultValue(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.loginRegistration.showAccountTypeSelection' => true,
                'core.loginRegistration.passwordMinLength' => '8',
            ],
            'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel' => true,
        ]);

        $result = static::createStub(EntitySearchResult::class);
        $customerEntity = new CustomerEntity();
        $customerEntity->setDoubleOptInRegistration(false);
        $customerEntity->setId('customer-1');
        $customerEntity->setGuest(false);
        $result->method('getEntities')->willReturn(new CustomerCollection([$customerEntity]));

        $salutationId = Uuid::randomHex();
        /** @var StaticEntityRepository<SalutationCollection> $salutationRepository */
        $salutationRepository = new StaticEntityRepository([[$salutationId]], new SalutationDefinition());

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->method('search')->willReturn($result);
        $customerRepository
            ->expects($this->once())
            ->method('create')
            ->willReturnCallback(static function (array $create) use ($salutationId) {
                static::assertCount(1, $create);
                static::assertArrayHasKey('salutationId', $create[0]);
                static::assertSame($create[0]['salutationId'], $salutationId);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
            });

        $register = $this->createRegisterRoute(
            salutationRepository: $salutationRepository,
            systemConfigService: $systemConfigService,
            customerRepository: $customerRepository
        );

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'countryId' => Uuid::randomHex(),
            ],
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'salutationId' => '',
        ];

        $salesChannelContext = Generator::generateSalesChannelContext();

        $register->register(new RequestDataBag($data), $salesChannelContext, false);
    }

    public function testSalutationIdIsAssignedToShippingAndBilling(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.loginRegistration.showAccountTypeSelection' => true,
                'core.loginRegistration.passwordMinLength' => '8',
            ],
            'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel' => true,
        ]);

        $result = static::createStub(EntitySearchResult::class);
        $customerEntity = new CustomerEntity();
        $customerEntity->setDoubleOptInRegistration(false);
        $customerEntity->setId('customer-1');
        $customerEntity->setGuest(false);
        $result->method('getEntities')->willReturn(new CustomerCollection([$customerEntity]));

        $salutationId = Uuid::randomHex();
        /** @var StaticEntityRepository<SalutationCollection> $salutationRepository */
        $salutationRepository = new StaticEntityRepository([[$salutationId]], new SalutationDefinition());

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->method('search')->willReturn($result);
        $customerRepository
            ->expects($this->once())
            ->method('create')
            ->willReturnCallback(static function (array $create) use ($salutationId) {
                static::assertCount(1, $create);
                static::assertArrayHasKey('salutationId', $create[0]);
                static::assertSame($create[0]['salutationId'], $salutationId);
                static::assertIsArray($create[0]['addresses']);
                static::assertCount(2, $create[0]['addresses']);
                foreach ($create[0]['addresses'] as $address) {
                    static::assertArrayHasKey('salutationId', $address);
                    static::assertSame($address['salutationId'], $salutationId);
                }

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
            });

        $register = $this->createRegisterRoute(
            salutationRepository: $salutationRepository,
            systemConfigService: $systemConfigService,
            customerRepository: $customerRepository
        );

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'countryId' => Uuid::randomHex(),
            ],
            'shippingAddress' => [
                'countryId' => Uuid::randomHex(),
            ],
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'salutationId' => '',
        ];

        $salesChannelContext = Generator::generateSalesChannelContext();

        $register->register(new RequestDataBag($data), $salesChannelContext, false);
    }

    public function testRedirectParameters(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.loginRegistration.passwordMinLength' => '8',
                'core.loginRegistration.doubleOptInRegistration' => true,
                'core.cart.wishlistEnabled' => true,
            ],
            'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel' => true,
        ]);

        $customerEntity = new CustomerEntity();
        $customerEntity->setDoubleOptInRegistration(true);
        $customerEntity->setId('customer-1');
        $customerEntity->setGuest(false);
        $customerEntity->setEmail('test@test.de');

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository(
            [new CustomerCollection([$customerEntity])],
            new CustomerDefinition(),
        );

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher
            ->expects($this->atLeast(1))
            ->method('dispatch')
            ->with(
                static::callback(static function (Event $event): bool {
                    if ($event instanceof CustomerDoubleOptInRegistrationEvent) {
                        $query = [];
                        $queryString = \parse_url($event->getConfirmUrl(), \PHP_URL_QUERY);
                        self::assertIsString($queryString);
                        \parse_str($queryString, $query);
                        self::assertArrayHasKey('productId', $query);
                        self::assertSame('018b906b869273fea7926f161dd23911', $query['productId']);
                    }

                    return true;
                })
            );

        $register = $this->createRegisterRoute(
            eventDispatcher: $eventDispatcher,
            systemConfigService: $systemConfigService,
            customerRepository: $customerRepository
        );

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'countryId' => Uuid::randomHex(),
            ],
            'storefrontUrl' => 'http://localhost:8000',
            'redirectTo' => 'frontend.wishlist.add.after.login',
            'redirectParameters' => '{"productId":"018b906b869273fea7926f161dd23911"}',
        ];

        $salesChannelContext = Generator::generateSalesChannelContext();

        $register->register(new RequestDataBag($data), $salesChannelContext, false);
    }

    public function testInvalidRedirectParameters(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.loginRegistration.passwordMinLength' => '8',
                'core.loginRegistration.doubleOptInRegistration' => true,
                'core.cart.wishlistEnabled' => true,
            ],
            'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel' => true,
        ]);

        $customerEntity = new CustomerEntity();
        $customerEntity->setDoubleOptInRegistration(true);
        $customerEntity->setId('customer-1');
        $customerEntity->setGuest(false);
        $customerEntity->setEmail('test@test.de');

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository(
            [new CustomerCollection([$customerEntity])],
            new CustomerDefinition(),
        );

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher
            ->expects($this->atLeast(1))
            ->method('dispatch')
            ->with(
                static::callback(static function ($event): bool {
                    if ($event instanceof CustomerDoubleOptInRegistrationEvent) {
                        $query = [];
                        $queryString = \parse_url($event->getConfirmUrl(), \PHP_URL_QUERY);
                        self::assertIsString($queryString);
                        \parse_str($queryString, $query);
                        self::assertArrayHasKey('redirectTo', $query);
                        self::assertSame('frontend.wishlist.add.after.login', $query['redirectTo']);
                    }

                    return true;
                })
            );

        $register = $this->createRegisterRoute(
            eventDispatcher: $eventDispatcher,
            systemConfigService: $systemConfigService,
            customerRepository: $customerRepository
        );

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'countryId' => Uuid::randomHex(),
            ],
            'storefrontUrl' => 'http://localhost:8000',
            'redirectTo' => 'frontend.wishlist.add.after.login',
            'redirectParameters' => 'thisisnotajson',
        ];

        $salesChannelContext = Generator::generateSalesChannelContext();

        $register->register(new RequestDataBag($data), $salesChannelContext, false);
    }

    public function testRegisterWithBillingAddressCountryViolation(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.loginRegistration.showAccountTypeSelection' => true,
                'core.loginRegistration.passwordMinLength' => '8',
            ],
            'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel' => true,
        ]);

        $customerEntity = new CustomerEntity();
        $customerEntity->setDoubleOptInRegistration(true);
        $customerEntity->setId('customer-1');
        $customerEntity->setGuest(false);
        $customerEntity->setEmail('test@test.de');

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository(
            [new CustomerCollection([$customerEntity])],
            new CustomerDefinition(),
        );

        $countryId = Uuid::randomHex();

        $country = new CountryEntity();
        $country->setId($countryId);
        $country->setVatIdRequired(true);

        $countryRepository = static::createStub(SalesChannelRepository::class);
        $countryRepository
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    CountryDefinition::ENTITY_NAME,
                    1,
                    new CountryCollection([$country]),
                    null,
                    new Criteria(),
                    Context::createDefaultContext()
                )
            );

        $salutationId = Uuid::randomHex();

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'countryId' => $countryId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $salutationId,
            ],
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'shippingAddress' => [
                'countryId' => $countryId,
                'id' => Uuid::randomHex(),
                'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
                'salutationId' => $salutationId,
            ],
            'salutationId' => $salutationId,
            'lastName' => 'Mustermann',
            'firstName' => 'Max',
            'vatIds' => ['123'],
            'storefrontUrl' => 'foo',
        ];

        $dataValidator = $this->createMock(DataValidator::class);
        $dataValidator
            ->expects($this->once())
            ->method('getViolations')
            ->with($data, static::callback(static function (DataValidationDefinition $definition) {
                $subs = $definition->getSubDefinitions();

                static::assertArrayHasKey('billingAddress', $subs);

                $billingAddressDefinition = $subs['billingAddress'];

                $properties = $billingAddressDefinition->getProperties();

                static::assertArrayHasKey('vatIds', $properties);
                static::assertCount(3, $properties['vatIds']);

                static::assertInstanceOf(NotBlank::class, $properties['vatIds'][0]);
                static::assertInstanceOf(Type::class, $properties['vatIds'][1]);
                static::assertInstanceOf(CustomerVatIdentification::class, $properties['vatIds'][2]);

                return true;
            }));

        $definitionFactory = static::createStub(DataValidationFactoryInterface::class);
        $definitionFactory
            ->method('create')
            ->willReturn(new DataValidationDefinition());

        $doubleOptInService = static::createStub(DoubleOptInService::class);
        $doubleOptInService->method('mapCustomerDoubleOptInData')->willReturnArgument(0);

        $registerRoute = new RegisterRoute(
            new EventDispatcher(),
            static::createStub(NumberRangeValueGeneratorInterface::class),
            $dataValidator,
            $definitionFactory,
            $definitionFactory,
            $systemConfigService,
            $customerRepository,
            static::createStub(SalesChannelContextPersister::class),
            $countryRepository,
            static::createStub(Connection::class),
            static::createStub(SalesChannelContextService::class),
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
            $definitionFactory,
            $doubleOptInService,
            new NativeClock(),
            $this->createIdempotencyGuard(),
            'test-app-secret',
        );

        $salesChannelContext = Generator::generateSalesChannelContext();

        $registerRoute->register(new RequestDataBag($data), $salesChannelContext, false);
    }

    public function testRegisterWithoutBillingAddressCountryViolation(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.loginRegistration.showAccountTypeSelection' => true,
                'core.loginRegistration.passwordMinLength' => '8',
            ],
            'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel' => true,
        ]);

        $customerEntity = new CustomerEntity();
        $customerEntity->setDoubleOptInRegistration(true);
        $customerEntity->setId('customer-1');
        $customerEntity->setGuest(false);
        $customerEntity->setEmail('test@test.de');

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository(
            [new CustomerCollection([$customerEntity])],
            new CustomerDefinition(),
        );

        $countryId = Uuid::randomHex();

        $country = new CountryEntity();
        $country->setId($countryId);

        $countryRepository = static::createStub(SalesChannelRepository::class);
        $countryRepository
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    CountryDefinition::ENTITY_NAME,
                    1,
                    new CountryCollection([$country]),
                    null,
                    new Criteria(),
                    Context::createDefaultContext()
                )
            );

        $salutationId = Uuid::randomHex();

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $salutationId,
            ],
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'shippingAddress' => [
                'id' => Uuid::randomHex(),
                'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
                'salutationId' => $salutationId,
            ],
            'salutationId' => $salutationId,
            'lastName' => 'Mustermann',
            'firstName' => 'Max',
            'storefrontUrl' => 'foo',
        ];

        $dataValidator = $this->createMock(DataValidator::class);
        $dataValidator
            ->expects($this->once())
            ->method('getViolations')
            ->with($data, static::callback(static function (DataValidationDefinition $definition) {
                $subs = $definition->getSubDefinitions();

                static::assertArrayHasKey('billingAddress', $subs);

                $billingAddressDefinition = $subs['billingAddress'];

                static::assertCount(6, $billingAddressDefinition->getProperties());
                static::assertArrayNotHasKey('vatIds', $billingAddressDefinition->getProperties());

                return true;
            }));

        $definitionFactory = static::createStub(DataValidationFactoryInterface::class);
        $definitionFactory
            ->method('create')
            ->willReturn(new DataValidationDefinition());

        $doubleOptInService = static::createStub(DoubleOptInService::class);
        $doubleOptInService->method('mapCustomerDoubleOptInData')->willReturnArgument(0);

        $registerRoute = new RegisterRoute(
            new EventDispatcher(),
            static::createStub(NumberRangeValueGeneratorInterface::class),
            $dataValidator,
            $definitionFactory,
            $definitionFactory,
            $systemConfigService,
            $customerRepository,
            static::createStub(SalesChannelContextPersister::class),
            $countryRepository,
            static::createStub(Connection::class),
            static::createStub(SalesChannelContextService::class),
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
            $definitionFactory,
            $doubleOptInService,
            new NativeClock(),
            $this->createIdempotencyGuard(),
            'test-app-secret',
        );

        $salesChannelContext = Generator::generateSalesChannelContext();

        $registerRoute->register(new RequestDataBag($data), $salesChannelContext, false);
    }

    #[TestDox('Rejects registration when billing address is not an associative array')]
    public function testRegisterWithNonArrayBillingAddressViolation(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.loginRegistration.showAccountTypeSelection' => true,
                'core.loginRegistration.passwordMinLength' => '8',
            ],
            'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel' => true,
        ]);

        $customerEntity = new CustomerEntity();
        $customerEntity->setDoubleOptInRegistration(true);
        $customerEntity->setId('customer-1');
        $customerEntity->setGuest(false);
        $customerEntity->setEmail('test@test.de');

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository(
            [new CustomerCollection([$customerEntity])],
            new CustomerDefinition(),
        );

        $countryId = Uuid::randomHex();

        $country = new CountryEntity();
        $country->setId($countryId);
        $country->setVatIdRequired(true);

        /** @var StaticSalesChannelRepository<CountryCollection> $countryRepository */
        $countryRepository = new StaticSalesChannelRepository([new CountryCollection([$country])]);

        $salutationId = Uuid::randomHex();

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => 'Max Mustermanns Address',
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'salutationId' => $salutationId,
            'lastName' => 'Mustermann',
            'firstName' => 'Max',
            'vatIds' => ['123'],
            'storefrontUrl' => 'foo',
        ];

        $dataValidator = $this->createMock(DataValidator::class);

        $violations = new ConstraintViolationList([
            new ConstraintViolation(
                'This value should be of type associative_array.',
                'This value should be of type {{ type }}.',
                ['{{ type }}' => 'associative_array'],
                'billingAddress',
                'billingAddress',
                'Max Mustermanns Address'
            ),
        ]);

        $dataValidator
            ->expects($this->once())
            ->method('getViolations')
            ->with($data, static::callback(static function (DataValidationDefinition $definition) {
                $subs = $definition->getSubDefinitions();

                static::assertArrayNotHasKey('billingAddress', $subs);

                $billingAddressConstraints = $definition->getProperty('billingAddress');
                static::assertCount(1, $billingAddressConstraints);

                $billingAddressConstraint = $billingAddressConstraints[0];
                static::assertInstanceOf(Type::class, $billingAddressConstraint);
                static::assertSame('associative_array', $billingAddressConstraint->type);

                return true;
            }))
            ->willReturn($violations);

        $definitionFactory = static::createStub(DataValidationFactoryInterface::class);
        $definitionFactory
            ->method('create')
            ->willReturn(new DataValidationDefinition());

        $registerRoute = new RegisterRoute(
            new EventDispatcher(),
            static::createStub(NumberRangeValueGeneratorInterface::class),
            $dataValidator,
            $definitionFactory,
            $definitionFactory,
            $systemConfigService,
            $customerRepository,
            static::createStub(SalesChannelContextPersister::class),
            $countryRepository,
            static::createStub(Connection::class),
            static::createStub(SalesChannelContextService::class),
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
            $definitionFactory,
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
            $this->createIdempotencyGuard(),
            'test-app-secret',
        );

        $salesChannelContext = Generator::generateSalesChannelContext();

        try {
            $registerRoute->register(new RequestDataBag($data), $salesChannelContext, false);
            static::fail('Expected ConstraintViolationException to be thrown');
        } catch (ConstraintViolationException $e) {
            static::assertCount(1, $e->getViolations());
            $violation = $e->getViolations()->get(0);
            static::assertSame('billingAddress', $violation->getPropertyPath());
            static::assertNotEmpty($violation->getMessage());
        }
    }

    #[TestDox('Accepts customer names with the maximum allowed length of 255 characters')]
    public function testRegisterAcceptsMaximumNameLengths(): void
    {
        $dataValidator = $this->createMock(DataValidator::class);
        $dataValidator
            ->expects($this->once())
            ->method('getViolations')
            ->willReturn(new ConstraintViolationList());

        $registerRoute = $this->createRegisterRoute(dataValidator: $dataValidator);

        $maxLengthFirstName = str_repeat('M', CustomerDefinition::MAX_LENGTH_FIRST_NAME);
        $maxLengthLastName = str_repeat('L', CustomerDefinition::MAX_LENGTH_LAST_NAME);

        $data = $this->createRegistrationData([
            'firstName' => $maxLengthFirstName,
            'lastName' => $maxLengthLastName,
            'billingAddress' => [
                'firstName' => $maxLengthFirstName,
                'lastName' => $maxLengthLastName,
                'countryId' => Uuid::randomHex(),
            ],
        ]);

        $registerRoute->register(
            new RequestDataBag($data),
            Generator::generateSalesChannelContext(),
            false
        );
    }

    #[TestDox('Rejects customer names exceeding the maximum allowed length of 255 characters')]
    public function testRegisterRejectsExcessiveNameLengths(): void
    {
        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'This value is too long. It should have 255 characters or less.',
            null,
            [],
            'root',
            'firstName',
            str_repeat('T', 256)
        ));

        $dataValidator = $this->createMock(DataValidator::class);
        $dataValidator
            ->expects($this->once())
            ->method('getViolations')
            ->willReturn($violations);

        $registerRoute = $this->createRegisterRoute(dataValidator: $dataValidator);

        $tooLongFirstName = str_repeat('T', CustomerDefinition::MAX_LENGTH_FIRST_NAME + 1);
        $tooLongLastName = str_repeat('L', CustomerDefinition::MAX_LENGTH_LAST_NAME + 1);

        $data = $this->createRegistrationData([
            'firstName' => $tooLongFirstName,
            'lastName' => $tooLongLastName,
            'billingAddress' => [
                'firstName' => $tooLongFirstName,
                'lastName' => $tooLongLastName,
                'countryId' => Uuid::randomHex(),
            ],
        ]);

        static::expectException(ConstraintViolationException::class);
        $registerRoute->register(
            new RequestDataBag($data),
            Generator::generateSalesChannelContext(),
            false
        );
    }

    public function testRegisterPreservesShippingAddressSalutation(): void
    {
        $customerEntity = new CustomerEntity();
        $customerEntity->setDoubleOptInRegistration(false);
        $customerEntity->setId('customer-1');
        $customerEntity->setGuest(false);

        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new CustomerCollection([$customerEntity]));

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->method('getDefinition')->willReturn(new CustomerDefinition());
        $customerRepository->method('search')->willReturn($result);
        $customerRepository
            ->expects($this->once())
            ->method('create')
            ->willReturnCallback(static function (array $create) {
                static::assertCount(1, $create);
                static::assertCount(2, $create[0]['addresses']);
                $billingAddress = array_values(array_filter(
                    $create[0]['addresses'],
                    static fn (array $address): bool => $address['firstName'] === 'John'
                ))[0];
                $shippingAddress = array_values(array_filter(
                    $create[0]['addresses'],
                    static fn (array $address): bool => $address['firstName'] === 'Jane'
                ))[0];

                static::assertSame('billing-salutation', $billingAddress['salutationId']);
                static::assertSame('shipping-salutation', $shippingAddress['salutationId']);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
            });

        $dataValidator = $this->createMock(DataValidator::class);
        $dataValidator
            ->expects($this->once())
            ->method('getViolations')
            ->willReturn(new ConstraintViolationList());

        $registerRoute = $this->createRegisterRoute(
            dataValidator: $dataValidator,
            customerRepository: $customerRepository
        );

        $registerRoute->register(
            new RequestDataBag($this->createRegistrationData([
                'salutationId' => 'billing-salutation',
                'shippingAddress' => [
                    'firstName' => 'Jane',
                    'lastName' => 'Doe',
                    'countryId' => Uuid::randomHex(),
                    'salutationId' => 'shipping-salutation',
                ],
            ])),
            Generator::generateSalesChannelContext(),
            false
        );
    }

    public function testRegisterTrimsMappedAddressStringFields(): void
    {
        $customerEntity = new CustomerEntity();
        $customerEntity->setDoubleOptInRegistration(false);
        $customerEntity->setId('customer-1');
        $customerEntity->setGuest(true);

        $result = new EntitySearchResult(
            CustomerDefinition::ENTITY_NAME,
            1,
            new CustomerCollection([$customerEntity]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->method('getDefinition')->willReturn(new CustomerDefinition());
        $customerRepository->method('search')->willReturn($result);
        $customerRepository
            ->expects($this->once())
            ->method('create')
            ->willReturnCallback(static function (array $create) {
                static::assertCount(1, $create);
                static::assertCount(2, $create[0]['addresses']);

                $billingAddress = array_values(array_filter(
                    $create[0]['addresses'],
                    static fn (array $address): bool => $address['city'] === 'Berlin'
                ))[0];
                $shippingAddress = array_values(array_filter(
                    $create[0]['addresses'],
                    static fn (array $address): bool => $address['city'] === 'Hamburg'
                ))[0];

                static::assertSame('Dr.', $billingAddress['title']);
                static::assertSame('Max', $billingAddress['firstName']);
                static::assertSame('Mustermann', $billingAddress['lastName']);
                static::assertSame('Main Street 1', $billingAddress['street']);
                static::assertSame('12345', $billingAddress['zipcode']);
                static::assertSame('Shopware', $billingAddress['company']);
                static::assertSame('Core', $billingAddress['department']);
                static::assertSame('123456', $billingAddress['phoneNumber']);
                static::assertSame('Line 1', $billingAddress['additionalAddressLine1']);
                static::assertSame('Line 2', $billingAddress['additionalAddressLine2']);
                static::assertSame(['note' => '  keep custom field whitespace  '], $billingAddress['customFields']);

                static::assertSame('Ms.', $shippingAddress['title']);
                static::assertSame('Jane', $shippingAddress['firstName']);
                static::assertSame('Doe', $shippingAddress['lastName']);
                static::assertSame('Side Street 2', $shippingAddress['street']);
                static::assertSame('54321', $shippingAddress['zipcode']);
                static::assertSame('Shopware Storefront', $shippingAddress['company']);
                static::assertSame('Design', $shippingAddress['department']);
                static::assertSame('654321', $shippingAddress['phoneNumber']);
                static::assertSame('Shipping Line 1', $shippingAddress['additionalAddressLine1']);
                static::assertSame('Shipping Line 2', $shippingAddress['additionalAddressLine2']);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
            });

        $dataValidator = static::createStub(DataValidator::class);
        $dataValidator
            ->method('getViolations')
            ->willReturn(new ConstraintViolationList());

        $addressValidationFactory = static::createStub(DataValidationFactoryInterface::class);
        $addressValidationFactory
            ->method('create')
            ->willReturnCallback(static fn (): DataValidationDefinition => new DataValidationDefinition('address.create'));

        $customFieldMapper = new StoreApiCustomFieldMapper(static::createStub(Connection::class), [
            CustomerAddressDefinition::ENTITY_NAME => [
                ['name' => 'note', 'type' => 'text'],
            ],
        ]);

        $registerRoute = $this->createRegisterRoute(
            dataValidator: $dataValidator,
            addressValidationFactory: $addressValidationFactory,
            customFieldMapper: $customFieldMapper,
            customerRepository: $customerRepository
        );

        $registerRoute->register(
            new RequestDataBag($this->createRegistrationData([
                'guest' => true,
                'title' => '  Dr.  ',
                'firstName' => "\nMax\t",
                'lastName' => "\rMustermann ",
                'billingAddress' => [
                    'countryId' => Uuid::randomHex(),
                    'street' => "\t Main Street 1 \n",
                    'zipcode' => '  12345  ',
                    'city' => "\rBerlin\n",
                    'company' => "\tShopware ",
                    'department' => "\nCore        ",
                    'phoneNumber' => "\t123456\n",
                    'additionalAddressLine1' => "\nLine 1 ",
                    'additionalAddressLine2' => "\tLine 2\r",
                    'customFields' => [
                        'note' => '  keep custom field whitespace  ',
                    ],
                ],
                'shippingAddress' => [
                    'title' => "\nMs.\t",
                    'firstName' => "\tJane ",
                    'lastName' => "          Doe\n",
                    'countryId' => Uuid::randomHex(),
                    'street' => "\nSide Street 2           ",
                    'zipcode' => "\t54321\n",
                    'city' => "\nHamburg\r",
                    'company' => "\tShopware Storefront\n",
                    'department' => '    Design    ',
                    'phoneNumber' => "\n654321 ",
                    'additionalAddressLine1' => " Shipping Line 1\n",
                    'additionalAddressLine2' => "\rShipping Line 2 ",
                ],
            ])),
            Generator::generateSalesChannelContext(),
            false
        );
    }

    public function testDuplicateRegistrationIsReplayed(): void
    {
        $customerId = Uuid::randomHex();
        $customer = $this->createActiveCustomer($customerId);

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository(
            [new CustomerCollection([$customer]), new CustomerCollection([$customer])],
            new CustomerDefinition(),
        );

        $contextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $contextService
            ->expects($this->once())
            ->method('get')
            ->willReturn(Generator::generateSalesChannelContext(token: self::WINNER_CONTEXT_TOKEN));

        $registerEvents = [];
        $loginEvents = [];
        $replayedEvents = [];
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(CustomerRegisterEvent::class, static function (CustomerRegisterEvent $event) use (&$registerEvents): void {
            $registerEvents[] = $event;
        });
        $eventDispatcher->addListener(GuestCustomerRegisterEvent::class, static function (GuestCustomerRegisterEvent $event) use (&$registerEvents): void {
            $registerEvents[] = $event;
        });
        $eventDispatcher->addListener(CustomerLoginEvent::class, static function (CustomerLoginEvent $event) use (&$loginEvents): void {
            $loginEvents[] = $event;
        });
        $eventDispatcher->addListener(CustomerRegistrationReplayedEvent::class, static function (CustomerRegistrationReplayedEvent $event) use (&$replayedEvents): void {
            $replayedEvents[] = $event;
        });

        $registerRoute = $this->createRegisterRoute(
            eventDispatcher: $eventDispatcher,
            customerRepository: $customerRepository,
            contextPersister: $this->createContextPersisterForReplay(self::WINNER_CONTEXT_TOKEN, $customerId),
            contextService: $contextService
        );

        $data = $this->createRegistrationData();

        $firstResponse = $registerRoute->register(new RequestDataBag($data), $this->createRequestContext(), false);
        $secondResponse = $registerRoute->register(new RequestDataBag($data), $this->createRequestContext(), false);

        static::assertCount(1, $customerRepository->creates);
        static::assertSame(self::WINNER_CONTEXT_TOKEN, $firstResponse->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame(self::WINNER_CONTEXT_TOKEN, $secondResponse->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame($customerId, $secondResponse->getCustomer()->getId());

        static::assertCount(1, $replayedEvents);
        $replayedEvent = current($replayedEvents);
        static::assertInstanceOf(CustomerRegistrationReplayedEvent::class, $replayedEvent);
        static::assertSame(self::WINNER_CONTEXT_TOKEN, $replayedEvent->contextToken);
        static::assertSame($customerId, $replayedEvent->customerId);

        static::assertCount(1, $registerEvents);
        static::assertCount(1, $loginEvents);
    }

    /**
     * @param (\Closure(CustomerEntity): void)|null $degradeCustomer
     * @param array<string, mixed> $persistedContext
     */
    #[DataProvider('staleOriginalResultProvider')]
    public function testDuplicateRegistrationRunsFreshWhenOriginalResultIsStale(
        ?\Closure $degradeCustomer,
        bool $replayFindsCustomer,
        array $persistedContext
    ): void {
        $customer = $this->createActiveCustomer(self::REPLAYED_CUSTOMER_ID);
        if ($degradeCustomer !== null) {
            $degradeCustomer($customer);
        }

        $replaySearchResult = $replayFindsCustomer ? [$customer] : [];

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository(
            [
                new CustomerCollection([$customer]),
                // the guard retries the replay once under the lock before it falls back to a fresh registration
                new CustomerCollection($replaySearchResult),
                new CustomerCollection($replaySearchResult),
                new CustomerCollection([$customer]),
            ],
            new CustomerDefinition(),
        );

        $contextPersister = static::createStub(SalesChannelContextPersister::class);
        $contextPersister->method('replace')->willReturn(self::WINNER_CONTEXT_TOKEN);
        $contextPersister->method('load')->willReturn($persistedContext);

        $replayedEvents = [];
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(CustomerRegistrationReplayedEvent::class, static function (CustomerRegistrationReplayedEvent $event) use (&$replayedEvents): void {
            $replayedEvents[] = $event;
        });

        $registerRoute = $this->createRegisterRoute(
            eventDispatcher: $eventDispatcher,
            customerRepository: $customerRepository,
            contextPersister: $contextPersister
        );

        $data = $this->createRegistrationData();

        $registerRoute->register(new RequestDataBag($data), $this->createRequestContext(), false);
        $registerRoute->register(new RequestDataBag($data), $this->createRequestContext(), false);

        static::assertCount(2, $customerRepository->creates);
        static::assertSame([], $replayedEvents);
    }

    /**
     * @return \Generator<string, array{(\Closure(CustomerEntity): void)|null, bool, array<string, mixed>}>
     */
    public static function staleOriginalResultProvider(): \Generator
    {
        $validPersistedContext = [
            'expired' => false,
            'token' => self::WINNER_CONTEXT_TOKEN,
            'customerId' => self::REPLAYED_CUSTOMER_ID,
        ];

        yield 'customer was deleted after the original registration' => [null, false, $validPersistedContext];

        yield 'customer was deactivated after the original registration' => [
            static function (CustomerEntity $customer): void {
                $customer->setActive(false);
            },
            true,
            $validPersistedContext,
        ];

        yield 'customer is bound to a different sales channel' => [
            static function (CustomerEntity $customer): void {
                $customer->setBoundSalesChannelId('different-sales-channel-id');
            },
            true,
            $validPersistedContext,
        ];

        yield 'persisted context no longer exists' => [null, true, []];

        yield 'persisted context is expired' => [null, true, [
            'expired' => true,
            'token' => self::WINNER_CONTEXT_TOKEN,
            'customerId' => self::REPLAYED_CUSTOMER_ID,
        ]];

        yield 'persisted context belongs to a different customer' => [null, true, [
            'expired' => false,
            'token' => self::WINNER_CONTEXT_TOKEN,
            'customerId' => 'ffffffffffffffffffffffffffffffff',
        ]];

        yield 'persisted context was rotated to a different token' => [null, true, [
            'expired' => false,
            'token' => 'rotated-context-token',
            'customerId' => self::REPLAYED_CUSTOMER_ID,
        ]];
    }

    public function testDuplicateDoubleOptInRegistrationIsReplayedWithoutToken(): void
    {
        $customerId = Uuid::randomHex();
        $customer = $this->createActiveCustomer($customerId);
        $customer->setDoubleOptInRegistration(true);

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository(
            [new CustomerCollection([$customer]), new CustomerCollection([$customer])],
            new CustomerDefinition(),
        );

        $doubleOptInService = $this->createMock(DoubleOptInService::class);
        $doubleOptInService->method('mapCustomerDoubleOptInData')->willReturnArgument(0);
        $doubleOptInService->expects($this->once())->method('sendDoubleOptInMail');

        $contextPersister = $this->createMock(SalesChannelContextPersister::class);
        $contextPersister->expects($this->never())->method('replace');
        $contextPersister->expects($this->never())->method('load');

        $contextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $contextService->expects($this->never())->method('get');

        $replayedEvents = [];
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(CustomerRegistrationReplayedEvent::class, static function (CustomerRegistrationReplayedEvent $event) use (&$replayedEvents): void {
            $replayedEvents[] = $event;
        });

        $registerRoute = $this->createRegisterRoute(
            eventDispatcher: $eventDispatcher,
            customerRepository: $customerRepository,
            contextPersister: $contextPersister,
            contextService: $contextService,
            doubleOptInService: $doubleOptInService
        );

        $data = $this->createRegistrationData(['storefrontUrl' => 'http://localhost:8000']);

        $firstResponse = $registerRoute->register(new RequestDataBag($data), $this->createRequestContext(), false);
        $secondResponse = $registerRoute->register(new RequestDataBag($data), $this->createRequestContext(), false);

        static::assertCount(1, $customerRepository->creates);
        static::assertFalse($firstResponse->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertFalse($secondResponse->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame($customerId, $secondResponse->getCustomer()->getId());
        static::assertSame([], $replayedEvents);
    }

    public function testDifferentPayloadWithSameTokenIsNotReplayed(): void
    {
        $customer = $this->createActiveCustomer(Uuid::randomHex());

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository(
            [new CustomerCollection([$customer]), new CustomerCollection([$customer])],
            new CustomerDefinition(),
        );

        $registerRoute = $this->createRegisterRoute(customerRepository: $customerRepository);

        $data = $this->createRegistrationData();
        $differentData = array_merge($data, ['email' => 'someone-else@example.com']);

        $registerRoute->register(new RequestDataBag($data), $this->createRequestContext(), false);
        $registerRoute->register(new RequestDataBag($differentData), $this->createRequestContext(), false);

        static::assertCount(2, $customerRepository->creates);
    }

    public function testStaleTokenReplaysFirstRegistrationAfterInterleavedDifferentRegistration(): void
    {
        $firstCustomerId = Uuid::randomHex();
        $firstCustomer = $this->createActiveCustomer($firstCustomerId);
        $interleavedCustomer = $this->createActiveCustomer(Uuid::randomHex());

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository(
            [
                new CustomerCollection([$firstCustomer]),
                new CustomerCollection([$interleavedCustomer]),
                new CustomerCollection([$firstCustomer]),
            ],
            new CustomerDefinition(),
        );

        $contextPersister = static::createStub(SalesChannelContextPersister::class);
        $contextPersister->method('replace')->willReturnOnConsecutiveCalls('first-new-token', 'interleaved-new-token');
        $contextPersister->method('load')->willReturn([
            'expired' => false,
            'token' => 'first-new-token',
            'customerId' => $firstCustomerId,
        ]);

        $contextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $contextService
            ->expects($this->exactly(2))
            ->method('get')
            ->willReturn(Generator::generateSalesChannelContext(token: 'first-new-token'));

        $replayedEvents = [];
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(CustomerRegistrationReplayedEvent::class, static function (CustomerRegistrationReplayedEvent $event) use (&$replayedEvents): void {
            $replayedEvents[] = $event;
        });

        $registerRoute = $this->createRegisterRoute(
            eventDispatcher: $eventDispatcher,
            customerRepository: $customerRepository,
            contextPersister: $contextPersister,
            contextService: $contextService
        );

        $data = $this->createRegistrationData();
        $interleavedData = array_merge($data, ['email' => 'someone-else@example.com']);

        $registerRoute->register(new RequestDataBag($data), $this->createRequestContext(), false);
        $registerRoute->register(new RequestDataBag($interleavedData), $this->createRequestContext(), false);
        $replayedResponse = $registerRoute->register(new RequestDataBag($data), $this->createRequestContext(), false);

        static::assertCount(2, $customerRepository->creates);
        static::assertSame($firstCustomerId, $replayedResponse->getCustomer()->getId());
        static::assertSame('first-new-token', $replayedResponse->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertCount(1, $replayedEvents);
    }

    public function testDuplicateRegistrationWithReorderedAssociativeKeysIsReplayed(): void
    {
        $customerId = Uuid::randomHex();
        $customer = $this->createActiveCustomer($customerId);

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository(
            [new CustomerCollection([$customer]), new CustomerCollection([$customer])],
            new CustomerDefinition(),
        );

        $replayedEvents = [];
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(CustomerRegistrationReplayedEvent::class, static function (CustomerRegistrationReplayedEvent $event) use (&$replayedEvents): void {
            $replayedEvents[] = $event;
        });

        $registerRoute = $this->createRegisterRoute(
            eventDispatcher: $eventDispatcher,
            customerRepository: $customerRepository,
            contextPersister: $this->createContextPersisterForReplay(self::WINNER_CONTEXT_TOKEN, $customerId)
        );

        $countryId = Uuid::randomHex();
        $salutationId = Uuid::randomHex();
        $data = [
            'email' => 'test@example.com',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'salutationId' => $salutationId,
            'billingAddress' => [
                'firstName' => 'John',
                'lastName' => 'Doe',
                'countryId' => $countryId,
            ],
        ];
        $reorderedData = [
            'billingAddress' => [
                'countryId' => $countryId,
                'lastName' => 'Doe',
                'firstName' => 'John',
            ],
            'salutationId' => $salutationId,
            'lastName' => 'Doe',
            'firstName' => 'John',
            'email' => 'test@example.com',
        ];

        $registerRoute->register(new RequestDataBag($data), $this->createRequestContext(), false);
        $secondResponse = $registerRoute->register(new RequestDataBag($reorderedData), $this->createRequestContext(), false);

        static::assertCount(1, $customerRepository->creates);
        static::assertSame(self::WINNER_CONTEXT_TOKEN, $secondResponse->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertCount(1, $replayedEvents);
    }

    public function testDuplicateRegistrationWithReorderedListValuesIsNotReplayed(): void
    {
        $customer = $this->createActiveCustomer(Uuid::randomHex());

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository(
            [new CustomerCollection([$customer]), new CustomerCollection([$customer])],
            new CustomerDefinition(),
        );

        $registerRoute = $this->createRegisterRoute(customerRepository: $customerRepository);

        $data = $this->createRegistrationData(['vatIds' => ['DE123456789', 'DE987654321']]);
        $reorderedData = array_merge($data, ['vatIds' => ['DE987654321', 'DE123456789']]);

        $registerRoute->register(new RequestDataBag($data), $this->createRequestContext(), false);
        $registerRoute->register(new RequestDataBag($reorderedData), $this->createRequestContext(), false);

        static::assertCount(2, $customerRepository->creates);
    }

    public function testDuplicateRegistrationWithDifferentExcludedTransportFieldsIsReplayed(): void
    {
        $customerId = Uuid::randomHex();
        $customer = $this->createActiveCustomer($customerId);

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository(
            [new CustomerCollection([$customer]), new CustomerCollection([$customer])],
            new CustomerDefinition(),
        );

        $replayedEvents = [];
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(CustomerRegistrationReplayedEvent::class, static function (CustomerRegistrationReplayedEvent $event) use (&$replayedEvents): void {
            $replayedEvents[] = $event;
        });

        $registerRoute = $this->createRegisterRoute(
            eventDispatcher: $eventDispatcher,
            customerRepository: $customerRepository,
            contextPersister: $this->createContextPersisterForReplay(self::WINNER_CONTEXT_TOKEN, $customerId)
        );

        $data = $this->createRegistrationData([
            'redirectTo' => 'frontend.account.home.page',
            'errorRoute' => 'frontend.account.register.page',
            '_grecaptcha_v3' => 'first-captcha-token',
            'shopware_basic_captcha_confirm' => 'first-captcha-answer',
        ]);
        $duplicateData = array_merge($data, [
            'redirectTo' => 'frontend.checkout.cart.page',
            'errorRoute' => 'frontend.checkout.register.page',
            '_grecaptcha_v3' => 'second-captcha-token',
            'shopware_basic_captcha_confirm' => 'second-captcha-answer',
        ]);

        $registerRoute->register(new RequestDataBag($data), $this->createRequestContext(), false);
        $secondResponse = $registerRoute->register(new RequestDataBag($duplicateData), $this->createRequestContext(), false);

        static::assertCount(1, $customerRepository->creates);
        static::assertSame(self::WINNER_CONTEXT_TOKEN, $secondResponse->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertCount(1, $replayedEvents);
    }

    public function testSamePayloadWithDifferentStorefrontUrlValidationModeIsNotReplayed(): void
    {
        $customer = $this->createActiveCustomer(Uuid::randomHex());

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository(
            [new CustomerCollection([$customer]), new CustomerCollection([$customer])],
            new CustomerDefinition(),
        );

        $registerRoute = $this->createRegisterRoute(customerRepository: $customerRepository);

        $data = $this->createRegistrationData(['storefrontUrl' => 'http://localhost:8000']);

        $registerRoute->register(new RequestDataBag($data), $this->createRequestContext(), false);
        $registerRoute->register(new RequestDataBag($data), $this->createRequestContext(), true);

        static::assertCount(2, $customerRepository->creates);
    }

    public function testSamePayloadWithDifferentAdditionalValidationDefinitionIsNotReplayed(): void
    {
        $customer = $this->createActiveCustomer(Uuid::randomHex());

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository(
            [new CustomerCollection([$customer]), new CustomerCollection([$customer])],
            new CustomerDefinition(),
        );

        $registerRoute = $this->createRegisterRoute(customerRepository: $customerRepository);

        $data = $this->createRegistrationData();

        $registerRoute->register(new RequestDataBag($data), $this->createRequestContext(), false, new DataValidationDefinition('x'));
        $registerRoute->register(new RequestDataBag($data), $this->createRequestContext(), false, new DataValidationDefinition('y'));

        static::assertCount(2, $customerRepository->creates);
    }

    /**
     * @return StaticEntityRepository<CustomerCollection>
     */
    private function createCustomerRepository(): StaticEntityRepository
    {
        $customerEntity = new CustomerEntity();
        $customerEntity->setDoubleOptInRegistration(false);
        $customerEntity->setId('customer-1');
        $customerEntity->setGuest(false);

        /** @var StaticEntityRepository<CustomerCollection> $repository */
        $repository = new StaticEntityRepository(
            [new CustomerCollection([$customerEntity])],
            new CustomerDefinition()
        );

        return $repository;
    }

    /**
     * @param EntityRepository<SalutationCollection>|null $salutationRepository
     * @param EntityRepository<CustomerCollection>|StaticEntityRepository<CustomerCollection>|null $customerRepository
     */
    private function createRegisterRoute(
        ?DataValidator $dataValidator = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?DataValidationFactoryInterface $addressValidationFactory = null,
        ?StoreApiCustomFieldMapper $customFieldMapper = null,
        ?EntityRepository $salutationRepository = null,
        ?StaticSystemConfigService $systemConfigService = null,
        EntityRepository|StaticEntityRepository|null $customerRepository = null,
        ?DataValidationFactoryInterface $accountValidationFactory = null,
        ?DataValidationFactoryInterface $passwordValidationFactory = null,
        ?SalesChannelContextPersister $contextPersister = null,
        ?SalesChannelContextServiceInterface $contextService = null,
        ?DoubleOptInService $doubleOptInService = null
    ): RegisterRoute {
        $dataValidator ??= static::createStub(DataValidator::class);
        $eventDispatcher ??= new EventDispatcher();
        $accountValidationFactory ??= static::createStub(DataValidationFactoryInterface::class);
        $addressValidationFactory ??= static::createStub(DataValidationFactoryInterface::class);
        $passwordValidationFactory ??= static::createStub(DataValidationFactoryInterface::class);
        $customFieldMapper ??= static::createStub(StoreApiCustomFieldMapper::class);
        $salutationRepository ??= static::createStub(EntityRepository::class);
        $systemConfigService ??= new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.loginRegistration.passwordMinLength' => '8',
            ],
            'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel' => true,
        ]);
        $customerRepository ??= $this->createCustomerRepository();
        $contextPersister ??= static::createStub(SalesChannelContextPersister::class);
        $contextService ??= static::createStub(SalesChannelContextService::class);

        if ($doubleOptInService === null) {
            $doubleOptInService = static::createStub(DoubleOptInService::class);
            $doubleOptInService->method('mapCustomerDoubleOptInData')->willReturnArgument(0);
        }

        return new RegisterRoute(
            $eventDispatcher,
            static::createStub(NumberRangeValueGeneratorInterface::class),
            $dataValidator,
            $accountValidationFactory,
            $addressValidationFactory,
            $systemConfigService,
            $customerRepository,
            $contextPersister,
            static::createStub(SalesChannelRepository::class),
            static::createStub(Connection::class),
            $contextService,
            $customFieldMapper,
            $salutationRepository,
            static::createStub(DataValidationFactoryInterface::class),
            $doubleOptInService,
            new NativeClock(),
            $this->createIdempotencyGuard(),
            'test-app-secret',
        );
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function createRegistrationData(array $overrides = []): array
    {
        return array_merge([
            'email' => 'test@example.com',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'salutationId' => Uuid::randomHex(),
            'billingAddress' => [
                'firstName' => 'John',
                'lastName' => 'Doe',
                'countryId' => Uuid::randomHex(),
            ],
        ], $overrides);
    }

    private function createIdempotencyGuard(): RegistrationIdempotencyGuard
    {
        return new RegistrationIdempotencyGuard(
            new LockFactory(new InMemoryStore()),
            new ArrayAdapter(),
            new NullLogger(),
        );
    }

    /**
     * Creates a fresh customer-less context per request, sharing the context token across the
     * requests of one double submission like the storefront session does.
     */
    private function createRequestContext(): SalesChannelContext
    {
        $context = Generator::generateSalesChannelContext(token: 'duplicated-request-token');
        $context->getSalesChannel()->setDomains(new SalesChannelDomainCollection());

        return $context;
    }

    private function createActiveCustomer(string $id): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId($id);
        $customer->setGuest(false);
        $customer->setActive(true);
        $customer->setDoubleOptInRegistration(false);

        return $customer;
    }

    private function createContextPersisterForReplay(string $newContextToken, string $customerId): SalesChannelContextPersister
    {
        $contextPersister = static::createStub(SalesChannelContextPersister::class);
        $contextPersister->method('replace')->willReturn($newContextToken);
        $contextPersister->method('load')->willReturn([
            'expired' => false,
            'token' => $newContextToken,
            'customerId' => $customerId,
        ]);

        return $contextPersister;
    }
}
