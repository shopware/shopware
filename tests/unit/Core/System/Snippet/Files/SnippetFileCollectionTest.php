<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Files;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\Snippet\Files\AbstractSnippetFile;
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

        $this->expectException(SnippetException::class);
        $this->expectExceptionMessage('The base snippet file for locale de-AT is not registered.');

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

    public function testHasFileForPathTracksFilesAddedAfterFirstLookup(): void
    {
        $firstFile = new MockSnippetFile('storefront.de-DE', 'de-DE');
        $secondFile = new MockSnippetFile('storefront.en-GB', 'en-GB');
        $collection = new SnippetFileCollection();

        static::assertFalse($collection->hasFileForPath($firstFile->getPath()));

        $collection->add($firstFile);
        static::assertTrue($collection->hasFileForPath($firstFile->getPath()));
        static::assertFalse($collection->hasFileForPath($secondFile->getPath()));

        $collection->add($secondFile);
        static::assertTrue($collection->hasFileForPath($firstFile->getPath()));
        static::assertTrue($collection->hasFileForPath($secondFile->getPath()));
    }

    public function testHasFileForPathDoesNotResolvePreviouslyIndexedFilesAfterAdd(): void
    {
        $firstFile = $this->createMock(AbstractSnippetFile::class);
        $firstFile->expects($this->once())->method('getPath')->willReturn(__FILE__);
        $secondFile = new MockSnippetFile('storefront.de-DE', 'de-DE');
        $collection = new SnippetFileCollection();
        $collection->add($firstFile);

        static::assertTrue($collection->hasFileForPath(__FILE__));

        $collection->add($secondFile);

        static::assertTrue($collection->hasFileForPath($secondFile->getPath()));
    }

    public function testHasFileForPathRebuildsAfterReplacementRemovalAndClear(): void
    {
        $firstFile = new MockSnippetFile('storefront.de-DE', 'de-DE');
        $secondFile = new MockSnippetFile('storefront.en-GB', 'en-GB');
        $collection = new SnippetFileCollection();
        $collection->set('file', $firstFile);

        static::assertTrue($collection->hasFileForPath($firstFile->getPath()));

        $collection->set('file', $secondFile);
        static::assertFalse($collection->hasFileForPath($firstFile->getPath()));
        static::assertTrue($collection->hasFileForPath($secondFile->getPath()));

        $collection->remove('file');
        static::assertFalse($collection->hasFileForPath($secondFile->getPath()));

        $collection->add($firstFile);
        static::assertTrue($collection->hasFileForPath($firstFile->getPath()));

        $collection->clear();
        static::assertFalse($collection->hasFileForPath($firstFile->getPath()));
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
