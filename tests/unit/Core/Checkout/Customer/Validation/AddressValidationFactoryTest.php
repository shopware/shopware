<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Validation;

use Monolog\Test\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Validation\AddressValidationFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Symfony\Component\Validator\Constraints\Length;
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

        $this->assertAddressDefinition($definition);

        if (Feature::isActive('v6.7.0.0')) {
            static::assertCount(9, $definition);
        }
    }

    public function testDefinitionRulesUpdate(): void
    {
        $definition = $this->addressValidationFactory->update($this->salesChannelContext)->getProperties();

        if (Feature::isActive('v6.7.0.0')) {
            static::assertCount(10, $definition);
            static::assertArrayHasKey('id', $definition);

            static::assertCount(2, $definition['id']);
            static::assertInstanceOf(NotBlank::class, $definition['id'][0]);
            static::assertInstanceOf(EntityExists::class, $definition['id'][1]);
        }

        $this->assertAddressDefinition($definition);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function assertAddressDefinition(array $definition): void
    {
        if (Feature::isActive('v6.7.0.0')) {
            static::assertArrayHasKey('title', $definition);
            static::assertInstanceOf(Length::class, $definition['title'][0]);
            static::assertArrayHasKey('zipcode', $definition);
            static::assertInstanceOf(Length::class, $definition['zipcode'][0]);
            static::assertCount(2, $definition['firstName']);
            static::assertInstanceOf(NotBlank::class, $definition['firstName'][0]);
            static::assertInstanceOf(Length::class, $definition['firstName'][1]);
            static::assertCount(2, $definition['lastName']);
            static::assertInstanceOf(NotBlank::class, $definition['lastName'][0]);
            static::assertInstanceOf(Length::class, $definition['lastName'][1]);
        } else {
            static::assertCount(7, $definition);
            static::assertCount(1, $definition['firstName']);
            static::assertInstanceOf(NotBlank::class, $definition['firstName'][0]);
            static::assertCount(1, $definition['lastName']);
            static::assertInstanceOf(NotBlank::class, $definition['lastName'][0]);
        }

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

        static::assertCount(1, $definition['city']);
        static::assertInstanceOf(NotBlank::class, $definition['city'][0]);

        static::assertCount(1, $definition['street']);
        static::assertInstanceOf(NotBlank::class, $definition['street'][0]);
    }
}
