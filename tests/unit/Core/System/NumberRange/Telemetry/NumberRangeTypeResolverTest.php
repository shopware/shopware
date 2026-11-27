<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\NumberRange\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\LabelMappingRegistry;
use Shopware\Core\System\NumberRange\Telemetry\NumberRangeTypeResolver;

/**
 * Resolver mechanics against a synthetic table; the shipped `number_range_type` table is covered by
 * {@see \Shopware\Tests\Integration\Core\Framework\Telemetry\ShippedLabelMappingsTest}.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(NumberRangeTypeResolver::class)]
class NumberRangeTypeResolverTest extends TestCase
{
    #[DataProvider('technicalNameProvider')]
    public function testResolve(?string $technicalName, string $expected): void
    {
        $resolver = new NumberRangeTypeResolver(new LabelMappingRegistry([
            NumberRangeTypeResolver::MAPPING => [
                'order' => 'order',
                'document_invoice' => 'document',
            ],
        ], 'other'));

        static::assertSame($expected, $resolver->resolve($technicalName));
    }

    /**
     * @return \Generator<string, array{0: ?string, 1: string}>
     */
    public static function technicalNameProvider(): \Generator
    {
        yield 'configured entry' => ['order', 'order'];
        yield 'configured entry folding to a group' => ['document_invoice', 'document'];
        yield 'null maps to other' => [null, 'other'];
        yield 'empty string maps to other' => ['', 'other'];
        yield 'unlisted type maps to other' => ['my_plugin_range', 'other'];
    }
}
