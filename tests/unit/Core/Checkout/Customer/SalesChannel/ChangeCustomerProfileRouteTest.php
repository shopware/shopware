<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CompanyAccountNameFields;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Extension\ChangeCustomerProfileRouteExtension;
use Shopware\Core\Checkout\Customer\SalesChannel\ChangeCustomerProfileRoute;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentification;
use Shopware\Core\Checkout\Customer\Validation\CustomerValidationFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiCustomFieldMapper;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ChangeCustomerProfileRoute::class)]
class ChangeCustomerProfileRouteTest extends TestCase
{
    public function testVatIdsAreNormalizedBeforeValidation(): void
    {
        $countryId = Uuid::randomHex();
        $country = new CountryEntity();
        $country->setId($countryId);
        $country->setCheckVatIdPattern(true);

        $billingAddress = new CustomerAddressEntity();
        $billingAddress->setCountryId($countryId);
        $billingAddress->setCountry($country);

        $customer = new CustomerEntity();
        $customer->setId('customer1');
        $customer->setDefaultBillingAddress($billingAddress);

        $validationFactory = static::createStub(CustomerValidationFactory::class);
        $validationFactory
            ->method('update')
            ->willReturn(new DataValidationDefinition());

        $validator = $this->createMock(DataValidator::class);
        $validator
            ->expects($this->once())
            ->method('validate')
            ->with(
                static::callback(static function (array $data): bool {
                    static::assertSame(['DE123456789'], $data['vatIds']);

                    return true;
                }),
                static::isInstanceOf(DataValidationDefinition::class)
            );

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository
            ->expects($this->once())
            ->method('update')
            ->with(
                static::callback(static function (array $data): bool {
                    static::assertSame(['DE123456789'], $data[0]['vatIds']);

                    return true;
                }),
                static::isInstanceOf(Context::class)
            );

        $change = new ChangeCustomerProfileRoute(
            $customerRepository,
            new EventDispatcher(),
            $validator,
            $validationFactory,
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
            new CompanyAccountNameFields(static::createStub(SystemConfigService::class)),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $data = new RequestDataBag([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'company' => 'Test Company',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => Uuid::randomHex(),
            'vatIds' => ['de 123456789'],
        ]);

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $change->change($data, $salesChannelContext, $customer);
    }

    public function testVatIdsAreValidatedAgainstTheActiveBillingCountry(): void
    {
        $defaultCountry = new CountryEntity();
        $defaultCountry->setId(Uuid::randomHex());
        $defaultCountry->setVatIdRequired(false);

        $defaultBillingAddress = new CustomerAddressEntity();
        $defaultBillingAddress->setCountryId($defaultCountry->getId());
        $defaultBillingAddress->setCountry($defaultCountry);

        $activeCountry = new CountryEntity();
        $activeCountry->setId(Uuid::randomHex());
        $activeCountry->setVatIdRequired(true);

        $activeBillingAddress = new CustomerAddressEntity();
        $activeBillingAddress->setCountryId($activeCountry->getId());
        $activeBillingAddress->setCountry($activeCountry);

        $customer = new CustomerEntity();
        $customer->setId('customer1');
        $customer->setDefaultBillingAddress($defaultBillingAddress);
        $customer->setActiveBillingAddress($activeBillingAddress);

        $validation = new DataValidationDefinition();
        $validationFactory = static::createStub(CustomerValidationFactory::class);
        $validationFactory
            ->method('update')
            ->willReturn($validation);

        $change = new ChangeCustomerProfileRoute(
            static::createStub(EntityRepository::class),
            new EventDispatcher(),
            static::createStub(DataValidator::class),
            $validationFactory,
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
            new CompanyAccountNameFields(static::createStub(SystemConfigService::class)),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $data = new RequestDataBag([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'company' => 'Test Company',
            'salutationId' => Uuid::randomHex(),
        ]);

        $change->change($data, static::createStub(SalesChannelContext::class), $customer);

        $vatIdConstraints = $validation->getProperty('vatIds');

        $vatIdentification = array_values(array_filter($vatIdConstraints, static fn (Constraint $constraint) => $constraint instanceof CustomerVatIdentification))[0] ?? null;
        static::assertInstanceOf(CustomerVatIdentification::class, $vatIdentification);
        static::assertSame($activeCountry->getId(), $vatIdentification->getCountryId());
        static::assertContainsEquals(new NotBlank(), $vatIdConstraints);
    }

    public function testCustomFieldsGetPassed(): void
    {
        $customFields = new RequestDataBag(['test1' => '1', 'test2' => '2']);

        $customerRepository = static::createStub(EntityRepository::class);

        $storeApiCustomFieldMapper = $this->createMock(StoreApiCustomFieldMapper::class);
        $storeApiCustomFieldMapper
            ->expects($this->once())
            ->method('map')
            ->with('customer', $customFields)
            ->willReturn(['test1' => '1']);

        $change = new ChangeCustomerProfileRoute(
            $customerRepository,
            new EventDispatcher(),
            static::createStub(DataValidator::class),
            static::createStub(CustomerValidationFactory::class),
            $storeApiCustomFieldMapper,
            static::createStub(EntityRepository::class),
            new CompanyAccountNameFields(static::createStub(SystemConfigService::class)),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $customer = new CustomerEntity();
        $customer->setId('customer1');
        $customer->setAccountType(CustomerEntity::ACCOUNT_TYPE_PRIVATE);
        $data = new RequestDataBag([
            'customFields' => $customFields,
            'salutationId' => '1',
        ]);

        $change->change($data, static::createStub(SalesChannelContext::class), $customer);
    }

    public function testAccountTypeGetPassed(): void
    {
        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository
            ->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data) {
                static::assertCount(1, $data);
                static::assertIsArray($data[0]);
                static::assertArrayHasKey('accountType', $data[0]);

                return true;
            }));

        $change = new ChangeCustomerProfileRoute(
            $customerRepository,
            new EventDispatcher(),
            static::createStub(DataValidator::class),
            static::createStub(CustomerValidationFactory::class),
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
            new CompanyAccountNameFields(static::createStub(SystemConfigService::class)),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $customer = new CustomerEntity();
        $customer->setId('customer1');
        $data = new RequestDataBag([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'salutationId' => '1',
        ]);

        $change->change($data, static::createStub(SalesChannelContext::class), $customer);
    }

    public function testBusinessCustomerKeepsCompanyAndVatIdsWhenTheRequestOmitsThem(): void
    {
        [$written, $validation] = $this->changeProfile($this->createBusinessCustomer(), [
            'firstName' => 'Jane',
            'lastName' => 'Doe',
        ]);

        static::assertSame('Jane', $written['firstName']);
        static::assertArrayNotHasKey('accountType', $written);
        static::assertArrayNotHasKey('company', $written);
        static::assertArrayNotHasKey('vatIds', $written);
        static::assertSame([], $validation->getProperty('company'));
        static::assertSame([], $validation->getProperty('vatIds'));
    }

    public function testBusinessCustomerChangesCompanyAndVatIdsSentWithoutAccountType(): void
    {
        [$written, $validation] = $this->changeProfile($this->createBusinessCustomer(), [
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'company' => 'New Company',
            'vatIds' => ['DE987654321'],
        ]);

        static::assertArrayNotHasKey('accountType', $written);
        static::assertSame('New Company', $written['company']);
        static::assertSame(['DE987654321'], $written['vatIds']);
        static::assertContainsEquals(CompanyAccountNameFields::companyNotBlank(), $validation->getProperty('company'));
        static::assertContainsEquals(new NotBlank(), $validation->getProperty('vatIds'));
    }

    public function testPrivateCustomerWithoutAccountTypeGetsNoCompany(): void
    {
        $customer = $this->createBusinessCustomer();
        $customer->setAccountType(CustomerEntity::ACCOUNT_TYPE_PRIVATE);

        [$written] = $this->changeProfile($customer, [
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'company' => 'New Company',
        ]);

        static::assertSame('', $written['company']);
        static::assertNull($written['vatIds']);
    }

    public function testSalutationIdIsAssignedDefaultValue(): void
    {
        $salutationId = Uuid::randomHex();

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository
            ->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data) use ($salutationId) {
                static::assertCount(1, $data);
                static::assertIsArray($data[0]);
                static::assertSame($data[0]['salutationId'], $salutationId);

                return true;
            }));

        $idSearchResult = new IdSearchResult(
            1,
            [$salutationId => ['data' => [], 'primaryKey' => $salutationId]],
            new Criteria(),
            Context::createDefaultContext(),
        );

        $salutationRepository = static::createStub(EntityRepository::class);
        $salutationRepository->method('searchIds')->willReturn($idSearchResult);

        $change = new ChangeCustomerProfileRoute(
            $customerRepository,
            new EventDispatcher(),
            static::createStub(DataValidator::class),
            static::createStub(CustomerValidationFactory::class),
            static::createStub(StoreApiCustomFieldMapper::class),
            $salutationRepository,
            new CompanyAccountNameFields(static::createStub(SystemConfigService::class)),
            new ExtensionDispatcher(new EventDispatcher())
        );

        $customer = new CustomerEntity();
        $customer->setId('customer1');

        $data = new RequestDataBag([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'salutationId' => '',
        ]);

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);

        $change->change($data, $salesChannelContext, $customer);
    }

    public function testPublishesExtension(): void
    {
        $data = new RequestDataBag();
        $context = Generator::generateSalesChannelContext();
        $customer = new CustomerEntity();
        $response = new SuccessResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('change-customer-profile-route.change.pre', static function (ChangeCustomerProfileRouteExtension $extension) use ($data, $context, $customer, $response): void {
            static::assertSame(['data' => $data, 'context' => $context, 'customer' => $customer], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ChangeCustomerProfileRoute(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            static::createStub(CustomerValidationFactory::class),
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
            new CompanyAccountNameFields(static::createStub(SystemConfigService::class)),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->change($data, $context, $customer));
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array{0: array<string, mixed>, 1: DataValidationDefinition}
     */
    private function changeProfile(CustomerEntity $customer, array $request): array
    {
        $validation = new DataValidationDefinition();
        $validationFactory = static::createStub(CustomerValidationFactory::class);
        $validationFactory->method('update')->willReturn($validation);

        $written = [];
        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository
            ->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data) use (&$written): bool {
                $written = $data[0];

                return true;
            }));

        $route = new ChangeCustomerProfileRoute(
            $customerRepository,
            new EventDispatcher(),
            static::createStub(DataValidator::class),
            $validationFactory,
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
            new CompanyAccountNameFields(static::createStub(SystemConfigService::class)),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $route->change(new RequestDataBag(['salutationId' => Uuid::randomHex(), ...$request]), static::createStub(SalesChannelContext::class), $customer);

        return [$written, $validation];
    }

    private function createBusinessCustomer(): CustomerEntity
    {
        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $country->setVatIdRequired(true);

        $billingAddress = new CustomerAddressEntity();
        $billingAddress->setCountryId($country->getId());
        $billingAddress->setCountry($country);

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setAccountType(CustomerEntity::ACCOUNT_TYPE_BUSINESS);
        $customer->setCompany('Example Inc.');
        $customer->setVatIds(['DE123456789']);
        $customer->setActiveBillingAddress($billingAddress);

        return $customer;
    }
}
