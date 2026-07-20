<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Validation\OrderValidationFactory;
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
#[CoversClass(OrderValidationFactory::class)]
class OrderValidationFactoryTest extends TestCase
{
    private SalesChannelContext $salesChannelContext;

    private SystemConfigService&MockObject $systemConfigService;

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

        $this->salesChannelContext = Generator::generateSalesChannelContext(customer: $customer);
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
    }

    public function testDefinitionRulesCreate(): void
    {
        $orderValidation = new OrderValidationFactory($this->systemConfigService);

        $this->systemConfigService->expects($this->once())->method('getBool')->willReturn(true);

        $definition = $orderValidation->create($this->salesChannelContext)->getProperties();

        static::assertCount(1, $definition);
        static::assertArrayHasKey('tos', $definition);

        static::assertCount(1, $definition['tos']);
        static::assertInstanceOf(NotBlank::class, $definition['tos'][0]);
    }

    public function testDefinitionRulesUpdate(): void
    {
        $orderValidation = new OrderValidationFactory($this->systemConfigService);

        $this->systemConfigService->expects($this->once())->method('getBool')->willReturn(true);

        $definition = $orderValidation->create($this->salesChannelContext)->getProperties();

        static::assertCount(1, $definition);
        static::assertInstanceOf(NotBlank::class, $definition['tos'][0]);
    }

    public function testDefinitionRulesCreateWithoutValidation(): void
    {
        $orderValidation = new OrderValidationFactory($this->systemConfigService);

        $this->systemConfigService->expects($this->once())->method('getBool')->willReturn(false);

        $definition = $orderValidation->create($this->salesChannelContext)->getProperties();

        static::assertCount(0, $definition);
        static::assertIsArray($definition);
    }

    public function testDefinitionRulesUpdateWithoutValidation(): void
    {
        $orderValidation = new OrderValidationFactory($this->systemConfigService);

        $this->systemConfigService->expects($this->once())->method('getBool')->willReturn(false);

        $definition = $orderValidation->update($this->salesChannelContext)->getProperties();

        static::assertCount(0, $definition);
        static::assertIsArray($definition);
    }
}
