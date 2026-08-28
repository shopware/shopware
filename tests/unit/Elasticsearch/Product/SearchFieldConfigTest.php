<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Product\SearchFieldConfig;

/**
 * @internal
 */
#[Package('framework')]
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

        static::assertSame('AUTO:5,10', $searchConfig->getFuzziness('foo'));
        static::assertSame('AUTO:5,10', $searchConfig->getFuzziness('f'));
        static::assertSame(0, $searchConfig->getFuzziness('123'));
        static::assertSame(0, $searchConfig->getFuzziness('1234'));
        static::assertSame(0, $searchConfig->getFuzziness('1234.5'));

        $searchConfig = new SearchFieldConfig('fooField', 1000.0, false);

        static::assertSame('AUTO:5,10', $searchConfig->getFuzziness('foo'));
        static::assertSame('AUTO:5,10', $searchConfig->getFuzziness('f'));
        static::assertSame(0, $searchConfig->getFuzziness('123'));
        static::assertSame(0, $searchConfig->getFuzziness('1234'));
        static::assertSame(0, $searchConfig->getFuzziness('1234.5'));
    }

    public function testGetPrefixLength(): void
    {
        $searchConfig = new SearchFieldConfig('fooField', 1000.0, true);

        static::assertSame(2, $searchConfig->getPrefixLength('foo'));
        static::assertSame(2, $searchConfig->getPrefixLength('bohrcraft'));
        static::assertSame(3, $searchConfig->getPrefixLength('bohrcraftX'));
        static::assertSame(3, $searchConfig->getPrefixLength('Tellerkopfschraube'));
    }

    public function testUseExactSubfield(): void
    {
        static::assertFalse((new SearchFieldConfig('fooField', 1000.0, true))->useExactSubfield());
        static::assertTrue((new SearchFieldConfig('fooField', 1000.0, true, false, true, true))->useExactSubfield());
    }

    public function testIsPhrase(): void
    {
        static::assertFalse((new SearchFieldConfig('fooField', 1000.0, true))->isPhrase());
        static::assertTrue((new SearchFieldConfig('fooField', 1000.0, true, false, true, false, true))->isPhrase());
    }

    public function testWithPhraseReturnsPhraseEnabledClone(): void
    {
        $config = new SearchFieldConfig('fooField', 1000.0, true, true, true, true);
        $phrase = $config->withPhrase();

        static::assertNotSame($config, $phrase);
        static::assertFalse($config->isPhrase(), 'the original must stay unchanged');
        static::assertTrue($phrase->isPhrase());

        // every other property is carried over to the clone
        static::assertSame('fooField', $phrase->getField());
        static::assertSame(1000.0, $phrase->getRanking());
        static::assertTrue($phrase->tokenize());
        static::assertTrue($phrase->isAndLogic());
        static::assertTrue($phrase->usePrefixMatch());
        static::assertTrue($phrase->useExactSubfield());
    }

    public function testWithoutNgramReturnsTokenizeDisabledClone(): void
    {
        $config = new SearchFieldConfig('fooField', 1000.0, true, true);
        $withoutNgram = $config->withoutNgram();

        static::assertNotSame($config, $withoutNgram);
        static::assertTrue($config->tokenize(), 'the original must stay unchanged');
        static::assertFalse($withoutNgram->tokenize());

        // every other property is carried over to the clone
        static::assertSame('fooField', $withoutNgram->getField());
        static::assertSame(1000.0, $withoutNgram->getRanking());
        static::assertTrue($withoutNgram->isAndLogic());
    }
}
