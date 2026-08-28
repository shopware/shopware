<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerEntity::class)]
class CustomerEntityTest extends TestCase
{
    protected function tearDown(): void
    {
        FieldVisibility::$isInTwigRenderingContext = false;
    }

    /**
     * @param \Closure(CustomerEntity): void $write
     * @param \Closure(CustomerEntity): mixed $read
     */
    #[TestDox('$_dataName is readable outside of a Twig rendering context')]
    #[DataProvider('internalPropertyProvider')]
    public function testInternalPropertyIsReadableOutsideTwig(\Closure $write, \Closure $read, mixed $expected, string $property): void
    {
        $customer = $this->customerWithInternalProperties();
        $write($customer);

        static::assertSame($expected, $read($customer));
    }

    /**
     * @param \Closure(CustomerEntity): void $write
     * @param \Closure(CustomerEntity): mixed $read
     */
    #[TestDox('$_dataName is guarded inside a Twig rendering context')]
    #[DataProvider('internalPropertyProvider')]
    public function testInternalPropertyIsGuardedInsideTwig(\Closure $write, \Closure $read, mixed $expected, string $property): void
    {
        $customer = $this->customerWithInternalProperties();
        $write($customer);

        FieldVisibility::$isInTwigRenderingContext = true;

        $this->expectExceptionObject(DataAbstractionLayerException::internalFieldAccessNotAllowed($property, CustomerEntity::class));
        $read($customer);
    }

    /**
     * @return \Generator<string, array{0: \Closure(CustomerEntity): void, 1: \Closure(CustomerEntity): mixed, 2: mixed, 3: string}>
     */
    public static function internalPropertyProvider(): \Generator
    {
        yield 'password' => [
            static fn (CustomerEntity $customer) => $customer->setPassword('secret'),
            static fn (CustomerEntity $customer) => $customer->getPassword(),
            'secret',
            'password',
        ];

        yield 'newsletterSalesChannelIds' => [
            static fn (CustomerEntity $customer) => $customer->setNewsletterSalesChannelIds(['a' => 'b']),
            static fn (CustomerEntity $customer) => $customer->getNewsletterSalesChannelIds(),
            ['a' => 'b'],
            'newsletterSalesChannelIds',
        ];

        yield 'legacyEncoder' => [
            static fn (CustomerEntity $customer) => $customer->setLegacyEncoder('md5'),
            static fn (CustomerEntity $customer) => $customer->getLegacyEncoder(),
            'md5',
            'legacyEncoder',
        ];

        yield 'legacyPassword' => [
            static fn (CustomerEntity $customer) => $customer->setLegacyPassword('hash'),
            static fn (CustomerEntity $customer) => $customer->getLegacyPassword(),
            'hash',
            'legacyPassword',
        ];
    }

    private function customerWithInternalProperties(): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->internalSetEntityData(
            CustomerDefinition::ENTITY_NAME,
            new FieldVisibility(['password', 'newsletterSalesChannelIds', 'legacyEncoder', 'legacyPassword']),
        );

        return $customer;
    }
}
