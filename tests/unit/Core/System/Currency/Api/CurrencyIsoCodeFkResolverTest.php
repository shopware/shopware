<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Currency\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Sync\FkReference;
use Shopware\Core\System\Currency\Api\CurrencyIsoCodeFkResolver;

/**
 * @internal
 */
#[CoversClass(CurrencyIsoCodeFkResolver::class)]
class CurrencyIsoCodeFkResolverTest extends TestCase
{
    public function testGetName(): void
    {
        static::assertSame('currency.iso_code', CurrencyIsoCodeFkResolver::getName());
    }

    public function testResolve(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'EUR' => 'eur00000000000000000000000000001',
                'USD' => 'usd00000000000000000000000000002',
            ]);

        $resolver = new CurrencyIsoCodeFkResolver($connection);

        $references = [
            new FkReference('ops/0/currencyId', 'currency', 'isoCode', 'EUR', false),
            new FkReference('ops/1/currencyId', 'currency', 'isoCode', 'USD', false),
            new FkReference('ops/2/currencyId', 'currency', 'isoCode', 'XXX', false),
        ];

        $result = $resolver->resolve($references);

        static::assertSame('eur00000000000000000000000000001', $result[0]->resolved);
        static::assertSame('usd00000000000000000000000000002', $result[1]->resolved);
        static::assertNull($result[2]->resolved);
    }

    public function testResolveIsCaseInsensitive(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->with(
                static::anything(),
                ['codes' => ['EUR', 'USD']],
                static::anything()
            )
            ->willReturn([
                'EUR' => 'eur00000000000000000000000000001',
                'USD' => 'usd00000000000000000000000000002',
            ]);

        $resolver = new CurrencyIsoCodeFkResolver($connection);

        $references = [
            new FkReference('ops/0/currencyId', 'currency', 'isoCode', 'eur', false),
            new FkReference('ops/1/currencyId', 'currency', 'isoCode', 'Usd', false),
        ];

        $result = $resolver->resolve($references);

        static::assertSame('eur00000000000000000000000000001', $result[0]->resolved);
        static::assertSame('usd00000000000000000000000000002', $result[1]->resolved);
    }

    public function testResolveWithEmptyInputDoesNotQuery(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllKeyValue');

        $resolver = new CurrencyIsoCodeFkResolver($connection);

        static::assertSame([], $resolver->resolve([]));
    }
}
