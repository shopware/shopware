<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderAddressService;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(OrderAddressService::class)]
class OrderAddressServiceTest extends TestCase
{
    /**
     * @param array<int, array{customerAddressId: string, type: string, deliveryId?: string}> $mappings
     */
    #[DataProvider('provideInvalidMappings')]
    public function testValidateInvalidMapping(array $mappings): void
    {
        $orderAddressService = new OrderAddressService(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class)
        );

        $this->expectException(OrderException::class);

        $orderAddressService->updateOrderAddresses(Uuid::randomHex(), $mappings, Context::createDefaultContext());
    }

    public static function provideInvalidMappings(): \Generator
    {
        yield 'missing type' => [
            'mapping' => [
                [
                    'customerAddressId' => '123',
                ],
            ],
        ];

        yield 'missing customerAddressId' => [
            'mapping' => [
                [
                    'type' => 'billing',
                ],
            ],
        ];

        yield 'invalid type' => [
            'mapping' => [
                [
                    'customerAddressId' => '123',
                    'type' => 'invalid',
                ],
            ],
        ];

        yield 'missing deliveryId' => [
            'mapping' => [
                [
                    'customerAddressId' => '123',
                    'type' => 'shipping',
                ],
            ],
        ];

        yield 'multiple billing addresses' => [
            'mapping' => [
                [
                    'customerAddressId' => '123',
                    'type' => 'billing',
                ],
                [
                    'customerAddressId' => '123',
                    'type' => 'billing',
                ],
            ],
        ];
    }

    public function testMissingOrder(): void
    {
        $orderAddressService = new OrderAddressService(
            new StaticEntityRepository([new OrderCollection([])]),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class)
        );

        $this->expectException(OrderException::class);

        $orderAddressService->updateOrderAddresses(Uuid::randomHex(), [], Context::createDefaultContext());
    }
}
