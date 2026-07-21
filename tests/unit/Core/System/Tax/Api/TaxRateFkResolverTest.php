<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Tax\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Sync\FkReference;
use Shopware\Core\System\Tax\Api\TaxRateFkResolver;

/**
 * @internal
 */
#[CoversClass(TaxRateFkResolver::class)]
class TaxRateFkResolverTest extends TestCase
{
    public function testGetName(): void
    {
        static::assertSame('tax.tax_rate', TaxRateFkResolver::getName());
    }

    public function testResolveOnlyResolvesUnambiguousRates(): void
    {
        $connection = $this->createMock(Connection::class);
        // The query filters out duplicate rates via HAVING COUNT(id) = 1, so 7.0 (ambiguous) is not returned
        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                '19.0' => 'tax19000000000000000000000000001',
                '0.0' => 'tax00000000000000000000000000002',
            ]);

        $resolver = new TaxRateFkResolver($connection);

        $references = [
            new FkReference('ops/0/taxId', 'tax', 'taxRate', 19.0, false),
            new FkReference('ops/1/taxId', 'tax', 'taxRate', 0.0, false),
            new FkReference('ops/2/taxId', 'tax', 'taxRate', 7.0, false),
            new FkReference('ops/3/taxId', 'tax', 'taxRate', 99.0, false),
        ];

        $result = $resolver->resolve($references);

        static::assertSame('tax19000000000000000000000000001', $result[0]->resolved);
        static::assertSame('tax00000000000000000000000000002', $result[1]->resolved);
        static::assertNull($result[2]->resolved, 'ambiguous rate should not resolve');
        static::assertNull($result[3]->resolved, 'unknown rate should not resolve');
    }

    public function testResolveAcceptsStringRateValues(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn(['19.0' => 'tax19000000000000000000000000001']);

        $resolver = new TaxRateFkResolver($connection);

        $references = [new FkReference('ops/0/taxId', 'tax', 'taxRate', '19.00', false)];

        $result = $resolver->resolve($references);

        static::assertSame('tax19000000000000000000000000001', $result[0]->resolved);
    }

    public function testResolveWithEmptyInputDoesNotQuery(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllKeyValue');

        $resolver = new TaxRateFkResolver($connection);

        static::assertSame([], $resolver->resolve([]));
    }
}
