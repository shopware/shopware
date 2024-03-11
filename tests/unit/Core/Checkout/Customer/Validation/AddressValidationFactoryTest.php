<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Validation;

use Monolog\Test\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Validation\AddressValidationFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AddressValidationFactory::class)]
class AddressValidationFactoryTest extends TestCase
{
    private AddressValidationFactory $addressValidationFactory;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $systemConfigServiceMock = $this->createMock(SystemConfigService::class);

        $this->addressValidationFactory = new AddressValidationFactory($systemConfigServiceMock);

        $customer = new CustomerEntity();

        $country = new CountryEntity();

        $address = new CustomerAddressEntity();
        $address->setId('foo');
        $address->setCountryId('foo');
        $address->setCountry($country);

        $customer->setActiveShippingAddress($address);
        $customer->setActiveBillingAddress($address);

        $this->salesChannelContext = Generator::createSalesChannelContext(customer: $customer);
    }

    public function testDefinitionRulesCreate(): void
    {
        $definition = $this->addressValidationFactory->create($this->salesChannelContext)->getProperties();

        static::assertCount(7, $definition);
        static::assertArrayHasKey('salutationId', $definition);
        static::assertArrayHasKey('countryId', $definition);
        static::assertArrayHasKey('countryStateId', $definition);
        static::assertArrayHasKey('firstName', $definition);
        static::assertArrayHasKey('lastName', $definition);
        static::assertArrayHasKey('street', $definition);
        static::assertArrayHasKey('city', $definition);

        static::assertCount(1, $definition['salutationId']);
        static::assertInstanceOf(EntityExists::class, $definition['salutationId'][0]);

        static::assertCount(3, $definition['countryId']);
        static::assertInstanceOf(EntityExists::class, $definition['countryId'][0]);
        static::assertInstanceOf(NotBlank::class, $definition['countryId'][1]);
        static::assertInstanceOf(EntityExists::class, $definition['countryId'][2]);

        static::assertCount(1, $definition['countryStateId']);
        static::assertInstanceOf(EntityExists::class, $definition['countryStateId'][0]);

        static::assertCount(1, $definition['firstName']);
        static::assertInstanceOf(NotBlank::class, $definition['firstName'][0]);

        static::assertCount(1, $definition['lastName']);
        static::assertInstanceOf(NotBlank::class, $definition['lastName'][0]);

        static::assertCount(1, $definition['street']);
        static::assertInstanceOf(NotBlank::class, $definition['street'][0]);

        static::assertCount(1, $definition['city']);
        static::assertInstanceOf(NotBlank::class, $definition['city'][0]);
    }

    public function testDefinitionRulesUpdate(): void
    {
        $definition = $this->addressValidationFactory->update($this->salesChannelContext)->getProperties();

        static::assertCount(8, $definition);
        static::assertArrayHasKey('salutationId', $definition);
        static::assertArrayHasKey('countryId', $definition);
        static::assertArrayHasKey('countryStateId', $definition);
        static::assertArrayHasKey('firstName', $definition);
        static::assertArrayHasKey('lastName', $definition);
        static::assertArrayHasKey('street', $definition);
        static::assertArrayHasKey('city', $definition);
        static::assertArrayHasKey('id', $definition);

        static::assertCount(1, $definition['salutationId']);
        static::assertInstanceOf(EntityExists::class, $definition['salutationId'][0]);

        static::assertCount(3, $definition['countryId']);
        static::assertInstanceOf(EntityExists::class, $definition['countryId'][0]);
        static::assertInstanceOf(NotBlank::class, $definition['countryId'][1]);
        static::assertInstanceOf(EntityExists::class, $definition['countryId'][2]);

        static::assertCount(1, $definition['countryStateId']);
        static::assertInstanceOf(EntityExists::class, $definition['countryStateId'][0]);

        static::assertCount(1, $definition['firstName']);
        static::assertInstanceOf(NotBlank::class, $definition['firstName'][0]);

        static::assertCount(1, $definition['lastName']);
        static::assertInstanceOf(NotBlank::class, $definition['lastName'][0]);

        static::assertCount(1, $definition['city']);
        static::assertInstanceOf(NotBlank::class, $definition['city'][0]);

        static::assertCount(1, $definition['street']);
        static::assertInstanceOf(NotBlank::class, $definition['street'][0]);

        static::assertCount(2, $definition['id']);
        static::assertInstanceOf(NotBlank::class, $definition['id'][0]);
        static::assertInstanceOf(EntityExists::class, $definition['id'][1]);
    }
}
