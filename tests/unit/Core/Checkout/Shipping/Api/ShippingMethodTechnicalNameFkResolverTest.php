<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Shipping\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\Api\ShippingMethodTechnicalNameFkResolver;
use Shopware\Core\Framework\Api\Sync\FkReference;

/**
 * @internal
 */
#[CoversClass(ShippingMethodTechnicalNameFkResolver::class)]
class ShippingMethodTechnicalNameFkResolverTest extends TestCase
{
    public function testGetName(): void
    {
        static::assertSame('shipping_method.technical_name', ShippingMethodTechnicalNameFkResolver::getName());
    }

    public function testResolve(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'shipping_standard' => 'standard0000000000000000000000001',
                'shipping_express' => 'express00000000000000000000000002',
            ]);

        $resolver = new ShippingMethodTechnicalNameFkResolver($connection);

        $references = [
            new FkReference('ops/0/shippingMethodId', 'shipping_method', 'technicalName', 'shipping_standard', false),
            new FkReference('ops/1/shippingMethodId', 'shipping_method', 'technicalName', 'shipping_express', false),
            new FkReference('ops/2/shippingMethodId', 'shipping_method', 'technicalName', 'shipping_unknown', false),
        ];

        $result = $resolver->resolve($references);

        static::assertSame('standard0000000000000000000000001', $result[0]->resolved);
        static::assertSame('express00000000000000000000000002', $result[1]->resolved);
        static::assertNull($result[2]->resolved);
    }

    public function testResolveWithEmptyInputDoesNotQuery(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllKeyValue');

        $resolver = new ShippingMethodTechnicalNameFkResolver($connection);

        static::assertSame([], $resolver->resolve([]));
    }
}
