<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\LineItemProductData;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(LineItemProductData::class)]
class LineItemProductDataTest extends TestCase
{
    #[DataProvider('typeProvider')]
    public function testIsExcludedFromProductConditions(string $type, bool $expected): void
    {
        $lineItem = new LineItem(Uuid::randomHex(), $type);

        static::assertSame($expected, LineItemProductData::isExcludedFromProductConditions($lineItem));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function typeProvider(): iterable
    {
        yield 'product' => [LineItem::PRODUCT_LINE_ITEM_TYPE, false];
        yield 'custom' => [LineItem::CUSTOM_LINE_ITEM_TYPE, false];
        yield 'credit' => [LineItem::CREDIT_LINE_ITEM_TYPE, false];
        yield 'plugin item' => ['my-plugin-item', false];
        yield 'container' => [LineItem::CONTAINER_LINE_ITEM, true];
        yield 'customized products option' => ['customized-products-option', true];
        yield 'customized products option value' => ['option-values', true];
    }
}
