<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Validation;

use Monolog\Test\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Validation\OrderValidationFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderValidationFactory::class)]
class OrderValidationFactoryTest extends TestCase
{
    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
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
        $orderValidation = new OrderValidationFactory();
        $definition = $orderValidation->create($this->salesChannelContext)->getProperties();

        static::assertCount(1, $definition);
        static::assertArrayHasKey('tos', $definition);

        static::assertCount(1, $definition['tos']);
        static::assertInstanceOf(NotBlank::class, $definition['tos'][0]);
    }

    public function testDefinitionRulesUpdate(): void
    {
        $orderValidation = new OrderValidationFactory();
        $definition = $orderValidation->create($this->salesChannelContext)->getProperties();

        static::assertCount(1, $definition);
        static::assertInstanceOf(NotBlank::class, $definition['tos'][0]);
    }
}
