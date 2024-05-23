<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute;
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
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiCustomFieldMapper;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
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
    public function testAccountType(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.loginRegistration.showAccountTypeSelection' => true,
                'core.loginRegistration.passwordMinLength' => '8',
            ],
            'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel' => true,
        ]);

        $result = $this->createMock(EntitySearchResult::class);
        $customerEntity = new CustomerEntity();
        $customerEntity->setDoubleOptInRegistration(false);
        $customerEntity->setId('customer-1');
        $customerEntity->setGuest(false);
        $result->method('first')->willReturn($customerEntity);

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->method('search')->willReturn($result);
        $customerRepository
            ->expects(static::once())
            ->method('create')
            ->willReturnCallback(function (array $create) {
                static::assertCount(1, $create);
                static::assertArrayHasKey('accountType', $create[0]);
                static::assertSame(CustomerEntity::ACCOUNT_TYPE_PRIVATE, $create[0]['accountType']);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
            });

        $register = new RegisterRoute(
            new EventDispatcher(),
            $this->createMock(NumberRangeValueGeneratorInterface::class),
            $this->createMock(DataValidator::class),
            $this->createMock(DataValidationFactoryInterface::class),
            $this->createMock(DataValidationFactoryInterface::class),
            $systemConfigService,
            $customerRepository,
            $this->createMock(SalesChannelContextPersister::class),
            $this->createMock(SalesChannelRepository::class),
            $this->createMock(Connection::class),
            $this->createMock(SalesChannelContextService::class),
            $this->createMock(StoreApiCustomFieldMapper::class),
            $this->createMock(EntityRepository::class),
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

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);

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

        $customerRepository = new StaticEntityRepository(
            [new CustomerCollection([$customerEntity])],
            new CustomerDefinition()
        );

        $definition = new DataValidationDefinition('address.create');

        $addressValidation = $this->createMock(DataValidationFactoryInterface::class);
        $addressValidation->method('create')->willReturn($definition);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function (Event $event) use ($definition) {
            if ($event instanceof BuildValidationEvent && $event->getName() === 'framework.validation.address.create') {
                $definition->add('company', new NotBlank());
                $definition->set('zipcode', new CustomerZipCode(['countryId' => null]));

                static::assertEquals($event->getDefinition()->getProperties(), $definition->getProperties());
            }

            return $event;
        });

        $register = new RegisterRoute(
            $dispatcher,
            $this->createMock(NumberRangeValueGeneratorInterface::class),
            new DataValidator(Validation::createValidatorBuilder()->getValidator()),
            $this->createMock(DataValidationFactoryInterface::class),
            $addressValidation,
            $systemConfigService,
            $customerRepository,
            $this->createMock(SalesChannelContextPersister::class),
            $this->createMock(SalesChannelRepository::class),
            $this->createMock(Connection::class),
            $this->createMock(SalesChannelContextService::class),
            $this->createMock(StoreApiCustomFieldMapper::class),
            $this->createMock(EntityRepository::class),
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

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);

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

        $customerRepository = new StaticEntityRepository(
            [new CustomerCollection([$customerEntity])],
            new CustomerDefinition()
        );

        $definition = new DataValidationDefinition('address.create');

        $addressValidation = $this->createMock(DataValidationFactoryInterface::class);
        $addressValidation->method('create')->willReturn($definition);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function (Event $event) {
            if ($event instanceof BuildValidationEvent && $event->getName() === 'framework.validation.address.create') {
                $definition = new DataValidationDefinition('address.create');

                $definition->add('company', new NotBlank());
                $definition->set('zipcode', new CustomerZipCode(['countryId' => null]));

                static::assertNull($event->getData()->get('shippingAddress'));
                static::assertSame($event->getData()->get('accountType'), CustomerEntity::ACCOUNT_TYPE_BUSINESS);
                static::assertEquals($event->getDefinition()->getProperties(), $definition->getProperties());
            }

            return $event;
        });

        $register = new RegisterRoute(
            $dispatcher,
            $this->createMock(NumberRangeValueGeneratorInterface::class),
            $this->createMock(DataValidator::class),
            $this->createMock(DataValidationFactoryInterface::class),
            $addressValidation,
            $systemConfigService,
            $customerRepository,
            $this->createMock(SalesChannelContextPersister::class),
            $this->createMock(SalesChannelRepository::class),
            $this->createMock(Connection::class),
            $this->createMock(SalesChannelContextService::class),
            $this->createMock(StoreApiCustomFieldMapper::class),
            $this->createMock(EntityRepository::class),
        );

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'id' => Uuid::randomHex(),
            ],
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
        ];

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);

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

        $customerRepository = new StaticEntityRepository(
            [new CustomerCollection([$customerEntity])],
            new CustomerDefinition(),
        );

        $definition = new DataValidationDefinition('address.create');

        $addressValidation = $this->createMock(DataValidationFactoryInterface::class);
        $addressValidation->method('create')->willReturn($definition);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function (Event $event) {
            if ($event instanceof BuildValidationEvent && $event->getName() === 'framework.validation.address.create') {
                $definition = new DataValidationDefinition('address.create');

                $definition->add('company', new NotBlank());
                $definition->set('zipcode', new CustomerZipCode(['countryId' => '123']));

                static::assertNull($event->getData()->get('shippingAddress'));
                static::assertSame($event->getData()->get('accountType'), CustomerEntity::ACCOUNT_TYPE_BUSINESS);
                static::assertEquals($event->getDefinition()->getProperties(), $definition->getProperties());
            }

            return $event;
        });

        $register = new RegisterRoute(
            $dispatcher,
            $this->createMock(NumberRangeValueGeneratorInterface::class),
            $this->createMock(DataValidator::class),
            $this->createMock(DataValidationFactoryInterface::class),
            $addressValidation,
            $systemConfigService,
            $customerRepository,
            $this->createMock(SalesChannelContextPersister::class),
            $this->createMock(SalesChannelRepository::class),
            $this->createMock(Connection::class),
            $this->createMock(SalesChannelContextService::class),
            $this->createMock(StoreApiCustomFieldMapper::class),
            $this->createMock(EntityRepository::class),
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

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);

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

        $result = $this->createMock(EntitySearchResult::class);
        $customerEntity = new CustomerEntity();
        $customerEntity->setDoubleOptInRegistration(false);
        $customerEntity->setId('customer-1');
        $customerEntity->setGuest(false);
        $result->method('first')->willReturn($customerEntity);

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->method('search')->willReturn($result);
        $customerRepository
            ->expects(static::once())
            ->method('create')
            ->willReturnCallback(function (array $create) {
                static::assertSame(['mapped' => 1], $create[0]['customFields']);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
            });

        $customFieldMapper = new StoreApiCustomFieldMapper($this->createMock(Connection::class), [
            CustomerDefinition::ENTITY_NAME => [
                ['name' => 'mapped', 'type' => 'int'],
            ],
        ]);

        $register = new RegisterRoute(
            new EventDispatcher(),
            $this->createMock(NumberRangeValueGeneratorInterface::class),
            $this->createMock(DataValidator::class),
            $this->createMock(DataValidationFactoryInterface::class),
            $this->createMock(DataValidationFactoryInterface::class),
            $systemConfigService,
            $customerRepository,
            $this->createMock(SalesChannelContextPersister::class),
            $this->createMock(SalesChannelRepository::class),
            $this->createMock(Connection::class),
            $this->createMock(SalesChannelContextService::class),
            $customFieldMapper,
            $this->createMock(EntityRepository::class),
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

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);

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

        $result = $this->createMock(EntitySearchResult::class);
        $customerEntity = new CustomerEntity();
        $customerEntity->setDoubleOptInRegistration(false);
        $customerEntity->setId('customer-1');
        $customerEntity->setGuest(false);
        $result->method('first')->willReturn($customerEntity);

        $salutationId = Uuid::randomHex();
        $salutationRepository = new StaticEntityRepository([[$salutationId]], new SalutationDefinition());

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->method('search')->willReturn($result);
        $customerRepository
            ->expects(static::once())
            ->method('create')
            ->willReturnCallback(function (array $create) use ($salutationId) {
                static::assertCount(1, $create);
                static::assertArrayHasKey('salutationId', $create[0]);
                static::assertSame($create[0]['salutationId'], $salutationId);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
            });

        $register = new RegisterRoute(
            new EventDispatcher(),
            $this->createMock(NumberRangeValueGeneratorInterface::class),
            $this->createMock(DataValidator::class),
            $this->createMock(DataValidationFactoryInterface::class),
            $this->createMock(DataValidationFactoryInterface::class),
            $systemConfigService,
            $customerRepository,
            $this->createMock(SalesChannelContextPersister::class),
            $this->createMock(SalesChannelRepository::class),
            $this->createMock(Connection::class),
            $this->createMock(SalesChannelContextService::class),
            $this->createMock(StoreApiCustomFieldMapper::class),
            $salutationRepository,
        );

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'countryId' => Uuid::randomHex(),
            ],
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'salutationId' => '',
        ];

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);

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

        $customerRepository = new StaticEntityRepository(
            [new CustomerCollection([$customerEntity])],
            new CustomerDefinition(),
        );

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher
            ->expects(static::atLeast(1))
            ->method('dispatch')
            ->with(
                static::callback(function (Event $event): bool {
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

        $register = new RegisterRoute(
            $eventDispatcher,
            $this->createMock(NumberRangeValueGeneratorInterface::class),
            $this->createMock(DataValidator::class),
            $this->createMock(DataValidationFactoryInterface::class),
            $this->createMock(DataValidationFactoryInterface::class),
            $systemConfigService,
            $customerRepository,
            $this->createMock(SalesChannelContextPersister::class),
            $this->createMock(SalesChannelRepository::class),
            $this->createMock(Connection::class),
            $this->createMock(SalesChannelContextService::class),
            $this->createMock(StoreApiCustomFieldMapper::class),
            $this->createMock(EntityRepository::class),
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

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);

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

        $customerRepository = new StaticEntityRepository(
            [new CustomerCollection([$customerEntity])],
            new CustomerDefinition(),
        );

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher
            ->expects(static::atLeast(1))
            ->method('dispatch')
            ->with(
                static::callback(function ($event): bool {
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

        $register = new RegisterRoute(
            $eventDispatcher,
            $this->createMock(NumberRangeValueGeneratorInterface::class),
            $this->createMock(DataValidator::class),
            $this->createMock(DataValidationFactoryInterface::class),
            $this->createMock(DataValidationFactoryInterface::class),
            $systemConfigService,
            $customerRepository,
            $this->createMock(SalesChannelContextPersister::class),
            $this->createMock(SalesChannelRepository::class),
            $this->createMock(Connection::class),
            $this->createMock(SalesChannelContextService::class),
            $this->createMock(StoreApiCustomFieldMapper::class),
            $this->createMock(EntityRepository::class),
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

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);

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

        $customerRepository = new StaticEntityRepository(
            [new CustomerCollection([$customerEntity])],
            new CustomerDefinition(),
        );

        $countryId = Uuid::randomHex();

        $country = new CountryEntity();
        $country->setId($countryId);
        $country->setVatIdRequired(true);

        $countryRepository = $this->createMock(SalesChannelRepository::class);
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
                'id' => Uuid::randomHex(),
                'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            ],
            'salutationId' => $salutationId,
            'lastName' => 'Mustermann',
            'firstName' => 'Max',
            'vatIds' => ['123'],
            'storefrontUrl' => 'foo',
        ];

        $dataValidator = $this->createMock(DataValidator::class);
        $dataValidator
            ->expects(static::once())
            ->method('getViolations')
            ->with($data, static::callback(function (DataValidationDefinition $definition) {
                $subs = $definition->getSubDefinitions();

                static::assertArrayHasKey('billingAddress', $subs);

                $billingAddressDefinition = $subs['billingAddress'];

                $properties = $billingAddressDefinition->getProperties();

                static::assertArrayHasKey('vatIds', $properties);
                static::assertCount(3, $properties['vatIds']);

                static::assertInstanceOf(NotBlank::class, $properties['vatIds'][0]);
                static::assertInstanceOf(Constraint::class, $properties['vatIds'][1]);
                static::assertInstanceOf(CustomerVatIdentification::class, $properties['vatIds'][2]);

                return true;
            }));

        $definitionFactory = $this->createMock(DataValidationFactoryInterface::class);
        $definitionFactory
            ->method('create')
            ->willReturn(new DataValidationDefinition());

        $registerRoute = new RegisterRoute(
            new EventDispatcher(),
            $this->createMock(NumberRangeValueGeneratorInterface::class),
            $dataValidator,
            $definitionFactory,
            $definitionFactory,
            $systemConfigService,
            $customerRepository,
            $this->createMock(SalesChannelContextPersister::class),
            $countryRepository,
            $this->createMock(Connection::class),
            $this->createMock(SalesChannelContextService::class),
            $this->createMock(StoreApiCustomFieldMapper::class),
            $this->createMock(EntityRepository::class),
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);

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

        $customerRepository = new StaticEntityRepository(
            [new CustomerCollection([$customerEntity])],
            new CustomerDefinition(),
        );

        $countryId = Uuid::randomHex();

        $country = new CountryEntity();
        $country->setId($countryId);

        $countryRepository = $this->createMock(SalesChannelRepository::class);
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
                'id' => Uuid::randomHex(),
                'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            ],
            'salutationId' => $salutationId,
            'lastName' => 'Mustermann',
            'firstName' => 'Max',
            'storefrontUrl' => 'foo',
        ];

        $dataValidator = $this->createMock(DataValidator::class);
        $dataValidator
            ->expects(static::once())
            ->method('getViolations')
            ->with($data, static::callback(function (DataValidationDefinition $definition) {
                $subs = $definition->getSubDefinitions();

                static::assertArrayHasKey('billingAddress', $subs);

                $billingAddressDefinition = $subs['billingAddress'];

                static::assertCount(5, $billingAddressDefinition->getProperties());
                static::assertArrayNotHasKey('vatIds', $billingAddressDefinition->getProperties());

                return true;
            }));

        $definitionFactory = $this->createMock(DataValidationFactoryInterface::class);
        $definitionFactory
            ->method('create')
            ->willReturn(new DataValidationDefinition());

        $registerRoute = new RegisterRoute(
            new EventDispatcher(),
            $this->createMock(NumberRangeValueGeneratorInterface::class),
            $dataValidator,
            $definitionFactory,
            $definitionFactory,
            $systemConfigService,
            $customerRepository,
            $this->createMock(SalesChannelContextPersister::class),
            $countryRepository,
            $this->createMock(Connection::class),
            $this->createMock(SalesChannelContextService::class),
            $this->createMock(StoreApiCustomFieldMapper::class),
            $this->createMock(EntityRepository::class),
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);

        $registerRoute->register(new RequestDataBag($data), $salesChannelContext, false);
    }
}
