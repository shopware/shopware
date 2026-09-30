<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\NumberRange\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\NumberRange\Telemetry\NumberRangeTypeResolver;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NumberRangeTypeResolver::class)]
class NumberRangeTypeResolverTest extends TestCase
{
    #[DataProvider('technicalNameProvider')]
    public function testResolve(?string $technicalName, string $expected): void
    {
        static::assertSame($expected, (new NumberRangeTypeResolver())->resolve($technicalName));
    }

    /**
     * @return \Generator<string, array{0: ?string, 1: string}>
     */
    public static function technicalNameProvider(): \Generator
    {
        // core type technical names map to their bounded group
        yield 'order maps to order' => ['order', 'order'];
        yield 'customer maps to customer' => ['customer', 'customer'];
        yield 'product maps to product' => ['product', 'product'];
        // all document types share the document group (own state rows, but never compared individually)
        yield 'document_invoice maps to document' => ['document_invoice', 'document'];
        yield 'document_delivery_note maps to document' => ['document_delivery_note', 'document'];
        yield 'document_credit_note maps to document' => ['document_credit_note', 'document'];
        yield 'document_storno maps to document' => ['document_storno', 'document'];

        // unmapped inputs fall through to other
        yield 'null maps to other' => [null, 'other'];
        yield 'empty string maps to other' => ['', 'other'];
        yield 'plugin custom range maps to other' => ['my_plugin_range', 'other'];
    }
}
