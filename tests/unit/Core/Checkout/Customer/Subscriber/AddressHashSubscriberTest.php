<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Subscriber\AddressHashSubscriber;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AddressHashSubscriber::class)]
class AddressHashSubscriberTest extends TestCase
{
    private AddressHashSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new AddressHashSubscriber();
    }

    /**
     * @param array<string, string|null> $expectedStruct
     */
    #[DataProvider('generateProvider')]
    public function testGenerate(CustomerAddressEntity|OrderAddressEntity $address, string $expectedHash, array $expectedStruct): void
    {
        $event = new EntityLoadedEvent(
            new CustomerAddressDefinition(),
            [$address],
            Context::createDefaultContext()
        );

        $this->subscriber->generateAddressHash($event);

        static::assertEquals($expectedHash, $address->getHash());
    }

    public static function generateProvider(): \Generator
    {
        $address = [
            'firstName' => 'address-first-name',
            'lastName' => 'address-last-name',
            'zipcode' => 'address-zipcode',
            'city' => 'address-city',
            'company' => 'address-company',
            'department' => 'address-department',
            'title' => 'address-title',
            'street' => 'address-street',
            'houseNumber' => 'address-house-number',
            'additionalAddressLine1' => 'address-additional-address-line-1',
            'additionalAddressLine2' => 'address-additional-address-line-2',
            'countryId' => 'address-country-id',
            'countryStateId' => 'address-country-state-id',
        ];

        yield 'OrderAddressEntity' => [
            (new OrderAddressEntity())->assign($address),
            'c819b038ddcb47ee97aece9a1feaa8e83b4a77a3dae62a6e38d077afe19e5247',
            [...$address, 'extensions' => []],
        ];

        yield 'CustomerAddressEntity' => [
            (new CustomerAddressEntity())->assign($address),
            'c819b038ddcb47ee97aece9a1feaa8e83b4a77a3dae62a6e38d077afe19e5247',
            [...$address, 'extensions' => []],
        ];
    }
}
