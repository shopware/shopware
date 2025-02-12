<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\AddressHashStruct;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Event\AddressHashEvent;
use Shopware\Core\Checkout\Customer\Service\AddressHasher;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AddressHasher::class)]
#[CoversClass(AddressHashStruct::class)]
class AddressHasherTest extends TestCase
{
    private MockObject&EventDispatcherInterface $eventDispatcher;

    private AddressHasher $hasher;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->hasher = new AddressHasher($this->eventDispatcher);
    }

    /**
     * @param array<string, string|null> $expectedStruct
     */
    #[DataProvider('generateProvider')]
    public function testGenerate(CustomerAddressEntity|OrderAddressEntity $address, string $expectedHash, array $expectedStruct): void
    {
        $struct = null;

        $this->eventDispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->willReturnCallback(static function (AddressHashEvent $event) use (&$struct) {
                $struct = $event->hashStruct;

                return $event;
            });

        $hash = $this->hasher->generate($address);

        static::assertEquals($expectedHash, $hash);
        static::assertEquals($expectedStruct, $struct?->getVars());
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
            'additionalAddressLine1' => 'address-additional-address-line-1',
            'additionalAddressLine2' => 'address-additional-address-line-2',
            'countryId' => 'address-country-id',
            'countryStateId' => 'address-country-state-id',
        ];

        yield 'OrderAddressEntity' => [
            (new OrderAddressEntity())->assign($address),
            'a91dfbca74c1e0aac538133ed3be43094a6aebce5b76577eae7d20245ab2c2f4',
            [...$address, 'extensions' => []],
        ];

        yield 'CustomerAddressEntity' => [
            (new CustomerAddressEntity())->assign($address),
            'a91dfbca74c1e0aac538133ed3be43094a6aebce5b76577eae7d20245ab2c2f4',
            [...$address, 'extensions' => []],
        ];
    }
}
