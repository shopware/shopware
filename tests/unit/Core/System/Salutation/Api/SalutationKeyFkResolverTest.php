<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Salutation\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Sync\FkReference;
use Shopware\Core\System\Salutation\Api\SalutationKeyFkResolver;

/**
 * @internal
 */
#[CoversClass(SalutationKeyFkResolver::class)]
class SalutationKeyFkResolverTest extends TestCase
{
    public function testGetName(): void
    {
        static::assertSame('salutation.salutation_key', SalutationKeyFkResolver::getName());
    }

    public function testResolve(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'mr' => 'mr00000000000000000000000000001',
                'mrs' => 'mrs0000000000000000000000000002',
            ]);

        $resolver = new SalutationKeyFkResolver($connection);

        $references = [
            new FkReference('ops/0/salutationId', 'salutation', 'salutationKey', 'mr', false),
            new FkReference('ops/1/salutationId', 'salutation', 'salutationKey', 'mrs', false),
            new FkReference('ops/2/salutationId', 'salutation', 'salutationKey', 'unknown', false),
        ];

        $result = $resolver->resolve($references);

        static::assertSame('mr00000000000000000000000000001', $result[0]->resolved);
        static::assertSame('mrs0000000000000000000000000002', $result[1]->resolved);
        static::assertNull($result[2]->resolved);
    }

    public function testResolveWithEmptyInputDoesNotQuery(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllKeyValue');

        $resolver = new SalutationKeyFkResolver($connection);

        static::assertSame([], $resolver->resolve([]));
    }
}
