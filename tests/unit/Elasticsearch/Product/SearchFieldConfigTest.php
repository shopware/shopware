<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Product\SearchFieldConfig;

/**
 * @internal
 */
#[CoversClass(SearchFieldConfig::class)]
class SearchFieldConfigTest extends TestCase
{
    public function testInit(): void
    {
        $searchConfig = new SearchFieldConfig('fooField', 1000, true);

        static::assertSame('fooField', $searchConfig->getField());
        static::assertSame(1000, $searchConfig->getRanking());
        static::assertTrue($searchConfig->tokenize());
        static::assertFalse($searchConfig->isCustomField());

        $customFieldSearchConfig = new SearchFieldConfig('customFields.foo', 1000, true);

        static::assertTrue($customFieldSearchConfig->isCustomField());
    }
}
