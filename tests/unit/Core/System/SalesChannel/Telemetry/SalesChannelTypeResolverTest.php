<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Telemetry\SalesChannelTypeResolver;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SalesChannelTypeResolver::class)]
class SalesChannelTypeResolverTest extends TestCase
{
    #[DataProvider('typeProvider')]
    public function testResolve(string $typeId, string $expected): void
    {
        static::assertSame($expected, (new SalesChannelTypeResolver())->resolve($typeId));
    }

    public static function typeProvider(): \Generator
    {
        yield 'storefront' => [Defaults::SALES_CHANNEL_TYPE_STOREFRONT, 'storefront'];
        yield 'api' => [Defaults::SALES_CHANNEL_TYPE_API, 'api'];
        yield 'product comparison' => [Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON, 'product_comparison'];
        yield 'agentic commerce' => [Defaults::SALES_CHANNEL_TYPE_AGENTIC_COMMERCE, 'agentic_commerce'];

        yield 'unknown type id falls back to other' => ['unknown-type-id', 'other'];
        yield 'empty type id falls back to other' => ['', 'other'];
    }
}
