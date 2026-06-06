<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Files;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Tests\Unit\Core\System\Snippet\Mock\MockSnippetFile;

/**
 * @internal
 */
#[CoversClass(SnippetFileCollection::class)]
class SnippetFileCollectionTest extends TestCase
{
    public function testGet(): void
    {
        $collection = $this->getCollection();

        $result_en_GB = $collection->get('storefront.en-GB');
        $result_de_DE = $collection->get('storefront.de-DE');
        $result_NA = $collection->get('not.available');

        static::assertNotNull($result_en_GB);
        static::assertNotNull($result_de_DE);
        static::assertSame('en-GB', $result_en_GB->getIso());
        static::assertSame('de-DE', $result_de_DE->getIso());
        static::assertNull($result_NA);
    }

    public function testGetIsoList(): void
    {
        $isoList = $this->getCollection()->getIsoList();

        static::assertCount(2, $isoList);
        static::assertContains('de-DE', $isoList);
        static::assertContains('en-GB', $isoList);
    }

    public function testGetLanguageFilesByIso(): void
    {
        $collection = $this->getCollection();

        $result_en_GB = $collection->getSnippetFilesByIso('en-GB');
        $result_de_DE = $collection->getSnippetFilesByIso('de-DE');
        $result_empty = $collection->getSnippetFilesByIso('na-NA');
        $result_empty_two = $collection->getSnippetFilesByIso('');

        static::assertCount(1, $result_en_GB);
        static::assertCount(2, $result_de_DE);
        static::assertCount(0, $result_empty);
        static::assertCount(0, $result_empty_two);

        static::assertSame('en-GB', $result_en_GB[0]->getIso());
        static::assertSame('de-DE', $result_de_DE[0]->getIso());
        static::assertEmpty($result_empty);
        static::assertEmpty($result_empty_two);
    }

    public function testGetBaseFileByIsoExpectException(): void
    {
        $collection = $this->getCollection();

        $this->expectExceptionObject(SnippetException::snippetFileNotRegistered('de-AT'));

        $collection->getBaseFileByIso('de-AT');
    }

    public function testGetBaseFileByIso(): void
    {
        $collection = $this->getCollection();

        $result_en_GB = $collection->getBaseFileByIso('en-GB');
        $result_de_DE = $collection->getBaseFileByIso('de-DE');

        static::assertSame('en-GB', $result_en_GB->getIso());
        static::assertTrue($result_en_GB->isBase());
        static::assertSame('de-DE', $result_de_DE->getIso());
        static::assertTrue($result_de_DE->isBase());
    }

    public function testToArray(): void
    {
        $result = $this->getCollection()->toArray();

        static::assertCount(3, $result);

        $resultDe = array_filter($result, static fn (array $item) => $item['iso'] === 'de-DE');

        $resultEn = array_filter($result, static fn (array $item) => $item['iso'] === 'en-GB');

        static::assertCount(2, $resultDe);
        static::assertCount(1, $resultEn);
    }

    public function testGetSnippetFilesWithLocaleFallbackForNonRegionalLocale(): void
    {
        $collection = new SnippetFileCollection();
        $collection->add(new MockSnippetFile('storefront.de', 'de', '{}', true, 'SwagPlugin'));

        $result = $collection->getSnippetFilesWithLocaleFallback('de');
        static::assertCount(1, $result);
        static::assertSame('de', $result[0]->getIso());
    }

    public function testGetSnippetFilesWithLocaleFallbackDoesNotIncludeAgnosticLanguage(): void
    {
        $collection = new SnippetFileCollection();
        $collection->add(new MockSnippetFile('agnostic.de', 'de', '{}', true, 'SwagPlugin'));

        $result = $collection->getSnippetFilesWithLocaleFallback('de-AT');

        static::assertEmpty($result);
    }

    public function testGetSnippetFilesWithLocaleFallbackFallsBackToCanonicalForm(): void
    {
        $collection = new SnippetFileCollection();
        $collection->add(new MockSnippetFile('storefront.de-DE', 'de-DE', '{}', true, 'SwagPayPal'));

        $result = $collection->getSnippetFilesWithLocaleFallback('de-AT');

        static::assertCount(1, $result);
        static::assertSame('de-DE', $result[0]->getIso());
    }

    public function testGetSnippetFilesWithLocaleFallbackUsesStaticMapForEnglish(): void
    {
        $collection = new SnippetFileCollection();
        $collection->add(new MockSnippetFile('storefront.en-GB', 'en-GB', '{}', true, 'SwagPayPal'));

        $result = $collection->getSnippetFilesWithLocaleFallback('en-AU');

        static::assertCount(1, $result);
        static::assertSame('en-GB', $result[0]->getIso());
    }

    public function testGetSnippetFilesWithLocaleFallbackNoFallbackForNonCanonicalLanguage(): void
    {
        $collection = new SnippetFileCollection();
        $collection->add(new MockSnippetFile('storefront.da-DK', 'da-DK', '{}', true, 'SwagPlugin'));

        $result = $collection->getSnippetFilesWithLocaleFallback('da-GL');

        static::assertEmpty($result);
    }

    public function testGetSnippetFilesWithLocaleFallbackReturnsEmptyForUnknownLocale(): void
    {
        $collection = $this->getCollection();

        $result = $collection->getSnippetFilesWithLocaleFallback('fr-FR');

        static::assertEmpty($result);
    }

    public function testGetSnippetFilesWithLocaleFallbackCombinesBothPriorityLevels(): void
    {
        $collection = new SnippetFileCollection();
        $collection->add(new MockSnippetFile('canonical.de-DE', 'de-DE', '{}', false, 'SwagPlugin'));
        $collection->add(new MockSnippetFile('country.de-AT', 'de-AT', '{}', false, 'SwagPlugin'));

        $result = $collection->getSnippetFilesWithLocaleFallback('de-AT');

        static::assertCount(2, $result);
        static::assertSame('de-DE', $result[0]->getIso());
        static::assertSame('de-AT', $result[1]->getIso());
    }

    public function testGetSnippetFilesWithLocaleFallbackDoesNotDoubleCountCanonicalLocale(): void
    {
        $collection = new SnippetFileCollection();
        $collection->add(new MockSnippetFile('storefront.de-DE', 'de-DE', '{}', true, 'SwagPlugin'));

        $result = $collection->getSnippetFilesWithLocaleFallback('de-DE');

        static::assertCount(1, $result);
        static::assertSame('de-DE', $result[0]->getIso());
    }

    private function getCollection(): SnippetFileCollection
    {
        $collection = new SnippetFileCollection();
        $collection->add(new MockSnippetFile('storefront.de-DE', 'de-DE', '{}', true, 'SwagPlugin'));
        $collection->add(new MockSnippetFile('storefront.de-DE_extension', 'de-DE', '{}', false, 'SwagPlugin'));
        $collection->add(new MockSnippetFile('storefront.en-GB', 'en-GB', '{}', true));

        return $collection;
    }
}
