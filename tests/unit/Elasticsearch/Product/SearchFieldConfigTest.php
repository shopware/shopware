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
        $searchConfig = new SearchFieldConfig('fooField', 1000.0, true, true, false);

        static::assertSame('fooField', $searchConfig->getField());
        static::assertSame(1000.0, $searchConfig->getRanking());
        static::assertTrue($searchConfig->tokenize());
        static::assertFalse($searchConfig->isCustomField());
        static::assertTrue($searchConfig->isAndLogic());
        static::assertFalse($searchConfig->usePrefixMatch());

        $customFieldSearchConfig = new SearchFieldConfig('customFields.foo', 1000.0, true);

        static::assertTrue($customFieldSearchConfig->isCustomField());

        $searchConfig->setRanking(2500.5);
        static::assertSame(2500.5, $searchConfig->getRanking());
    }

    public function testGetFuzziness(): void
    {
        $searchConfig = new SearchFieldConfig('fooField', 1000.0, true);

        static::assertSame('auto', $searchConfig->getFuzziness('foo'));
        static::assertSame('auto', $searchConfig->getFuzziness('f'));
        static::assertSame(0, $searchConfig->getFuzziness('123'));
        static::assertSame(0, $searchConfig->getFuzziness('1234'));
        static::assertSame(0, $searchConfig->getFuzziness('1234.5'));

        $searchConfig = new SearchFieldConfig('fooField', 1000.0, false);

        static::assertSame(1, $searchConfig->getFuzziness('foo'));
        static::assertSame(1, $searchConfig->getFuzziness('f'));
        static::assertSame(0, $searchConfig->getFuzziness('123'));
        static::assertSame(0, $searchConfig->getFuzziness('1234'));
        static::assertSame(0, $searchConfig->getFuzziness('1234.5'));
    }
}
