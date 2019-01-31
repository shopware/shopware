<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\Files;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Exception\InvalidSnippetFileException;
use Shopware\Core\Framework\Snippet\Files\de_DE\SnippetFile_de_DE;
use Shopware\Core\Framework\Snippet\Files\en_EN\SnippetFile_en_GB;
use Shopware\Core\Framework\Snippet\Files\SnippetFileCollection;
use Shopware\Core\Framework\Test\Snippet\_fixtures\TestLanguageExtensionFile_de_DE;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;

class SnippetFileCollectionTest extends TestCase
{
    public function testGet(): void
    {
        $collection = $this->getCollection();

        $result_en_GB = $collection->get('messages.en_GB');
        $result_de_DE = $collection->get('messages.de_DE');
        $result_NA = $collection->get('not.available');

        $this->assertSame('en_GB', $result_en_GB->getIso());
        $this->assertSame('de_DE', $result_de_DE->getIso());
        $this->assertNull($result_NA);
    }

    public function testGetIsoList(): void
    {
        $collection = $this->getCollection();

        $expectedResult = ['de_DE', 'en_GB'];
        $result = $collection->getIsoList();

        $this->assertArraySubset($expectedResult, $result);
    }

    public function testGetLanguageFilesByIso(): void
    {
        $collection = $this->getCollection();

        $result_en_GB = $collection->getSnippetFilesByIso('en_GB');
        $result_de_DE = $collection->getSnippetFilesByIso('de_DE');
        $result_empty = $collection->getSnippetFilesByIso('na_NA');
        $result_empty_two = $collection->getSnippetFilesByIso('');

        $this->assertNotNull($result_en_GB);
        $this->assertNotNull($result_de_DE);
        $this->assertNotNull($result_empty);
        $this->assertNotNull($result_empty_two);

        $this->assertCount(1, $result_en_GB);
        $this->assertCount(2, $result_de_DE);
        $this->assertCount(0, $result_empty);
        $this->assertCount(0, $result_empty_two);

        $this->assertSame('en_GB', $result_en_GB[0]->getIso());
        $this->assertSame('de_DE', $result_de_DE[0]->getIso());
        $this->assertSame([], $result_empty);
        $this->assertSame([], $result_empty_two);
    }

    public function testGetBaseFileByIso_expectException(): void
    {
        $collection = $this->getCollection();

        $this->expectException(InvalidSnippetFileException::class);

        $collection->getBaseFileByIso('de_AT');
    }

    public function testGetBaseFileByIso(): void
    {
        $collection = $this->getCollection();

        $result_en_GB = $collection->getBaseFileByIso('en_GB');
        $result_de_DE = $collection->getBaseFileByIso('de_DE');

        $this->assertSame('en_GB', $result_en_GB->getIso());
        $this->assertSame('de_DE', $result_de_DE->getIso());
    }

    public function testGetListSortedByIso(): void
    {
        $collection = $this->getCollection();
        $method = ReflectionHelper::getMethod(SnippetFileCollection::class, 'getListSortedByIso');

        $result = $method->invoke($collection);
        $expectedResult = ['de_DE' => [], 'en_GB' => []];

        $this->assertArraySubset($expectedResult, $result);
    }

    private function getCollection(): SnippetFileCollection
    {
        $collection = new SnippetFileCollection(new ArrayCollection([]));
        $collection->add(new TestLanguageExtensionFile_de_DE());
        $collection->add(new SnippetFile_de_DE());
        $collection->add(new SnippetFile_en_GB());

        return $collection;
    }
}
