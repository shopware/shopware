<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\LabelMappingRegistry;
use Shopware\Core\System\SalesChannel\Telemetry\SalesChannelTypeResolver;

/**
 * Resolver mechanics against a synthetic table; the shipped `sales_channel_type` table is covered by
 * {@see \Shopware\Tests\Integration\Core\Framework\Telemetry\ShippedLabelMappingsTest}.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SalesChannelTypeResolver::class)]
class SalesChannelTypeResolverTest extends TestCase
{
    #[DataProvider('typeProvider')]
    public function testResolve(string $typeId, string $expected): void
    {
        $resolver = new SalesChannelTypeResolver(new LabelMappingRegistry([
            SalesChannelTypeResolver::MAPPING => [
                Defaults::SALES_CHANNEL_TYPE_STOREFRONT => 'storefront',
            ],
        ], 'other'));

        static::assertSame($expected, $resolver->resolve($typeId));
    }

    public static function typeProvider(): \Generator
    {
        yield 'configured type id' => [Defaults::SALES_CHANNEL_TYPE_STOREFRONT, 'storefront'];
        yield 'unknown type id falls back to other' => ['unknown-type-id', 'other'];
        yield 'empty type id falls back to other' => ['', 'other'];
    }
}
