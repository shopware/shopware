<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\Files;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\Snippet\Mock\MockSnippetFile;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\System\Snippet\Exception\InvalidSnippetFileException;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;

class SnippetFileCollectionTest extends TestCase
{
    public static function tearDownAfterClass(): void
    {
        foreach (glob(__DIR__ . '/../Mock/_fixtures/*.json') as $mockFile) {
            unlink($mockFile);
        }
    }

    public function testGet(): void
    {
        $collection = $this->getCollection();

        $result_en_GB = $collection->get('storefront.en-GB');
        $result_de_DE = $collection->get('storefront.de-DE');
        $result_NA = $collection->get('not.available');

        static::assertSame('en-GB', $result_en_GB->getIso());
        static::assertSame('de-DE', $result_de_DE->getIso());
        static::assertNull($result_NA);
    }

    public function testGetIsoList(): void
    {
        $collection = $this->getCollection();
        $isoList = $collection->getIsoList();

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

        static::assertNotNull($result_en_GB);
        static::assertNotNull($result_de_DE);
        static::assertNotNull($result_empty);
        static::assertNotNull($result_empty_two);

        static::assertCount(1, $result_en_GB);
        static::assertCount(2, $result_de_DE);
        static::assertCount(0, $result_empty);
        static::assertCount(0, $result_empty_two);

        static::assertSame('en-GB', $result_en_GB[0]->getIso());
        static::assertSame('de-DE', $result_de_DE[0]->getIso());
        static::assertSame([], $result_empty);
        static::assertSame([], $result_empty_two);
    }

    public function testGetBaseFileByIsoExpectException(): void
    {
        $collection = $this->getCollection();

        $this->expectException(InvalidSnippetFileException::class);

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

    public function testGetListSortedByIso(): void
    {
        $collection = $this->getCollection();
        $method = ReflectionHelper::getMethod(SnippetFileCollection::class, 'getListSortedByIso');

        $result = $method->invoke($collection);

        static::assertCount(2, $result);
        static::assertArrayHasKey('de-DE', $result);
        static::assertCount(2, $result['de-DE']);
        static::assertArrayHasKey('en-GB', $result);
        static::assertCount(1, $result['en-GB']);
    }

    private function getCollection(): SnippetFileCollection
    {
        $collection = new SnippetFileCollection([]);
        $collection->add(new MockSnippetFile('storefront.de-DE', 'de-DE', '{}', true));
        $collection->add(new MockSnippetFile('storefront.de-DE_extension', 'de-DE', '{}', false));
        $collection->add(new MockSnippetFile('storefront.en-GB', 'en-GB', '{}', true));

        return $collection;
    }
}
