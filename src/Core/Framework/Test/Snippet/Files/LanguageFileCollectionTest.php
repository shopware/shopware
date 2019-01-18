<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\Files;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Exception\InvalidLanguageFileException;
use Shopware\Core\Framework\Snippet\Files\de_DE\LanguageFile_de_DE;
use Shopware\Core\Framework\Snippet\Files\en_EN\LanguageFile_en_GB;
use Shopware\Core\Framework\Snippet\Files\LanguageFileCollection;
use Shopware\Core\Framework\Test\Snippet\_fixtures\TestLanguageExtensionFile_de_DE;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;

class LanguageFileCollectionTest extends TestCase
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

        $result_en_GB = $collection->getLanguageFilesByIso('en_GB');
        $result_de_DE = $collection->getLanguageFilesByIso('de_DE');
        $result_empty = $collection->getLanguageFilesByIso('na_NA');
        $result_empty_two = $collection->getLanguageFilesByIso('');

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

        $this->expectException(InvalidLanguageFileException::class);

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
        $method = ReflectionHelper::getMethod(LanguageFileCollection::class, 'getListSortedByIso');

        $result = $method->invoke($collection);
        $expectedResult = ['de_DE' => [], 'en_GB' => []];

        $this->assertArraySubset($expectedResult, $result);
    }

    private function getCollection(): LanguageFileCollection
    {
        $collection = new LanguageFileCollection(new ArrayCollection([]));
        $collection->add(new TestLanguageExtensionFile_de_DE());
        $collection->add(new LanguageFile_de_DE());
        $collection->add(new LanguageFile_en_GB());

        return $collection;
    }
}
