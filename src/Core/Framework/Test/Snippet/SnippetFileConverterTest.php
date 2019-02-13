<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\Services;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Snippet\Aggregate\SnippetSet\SnippetSetEntity;
use Shopware\Core\Framework\Snippet\Files\SnippetFileCollection;
use Shopware\Core\Framework\Snippet\Files\SnippetFileInterface;
use Shopware\Core\Framework\Snippet\SnippetFileConverter;
use Shopware\Core\Framework\Snippet\SnippetFlattener;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testConvert\SnippetFile_de_DE;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testConvert\SnippetFile_en_GB;
use Shopware\Core\Framework\Test\TestCaseBase\AssertArraySubsetBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;

class SnippetFileConverterTest extends TestCase
{
    use KernelTestBehaviour,
        AssertArraySubsetBehaviour;

    /**
     * @param SnippetSetEntity $struct
     *
     * @dataProvider dataProviderForTestConvert
     */
    public function testConvert(SnippetSetEntity $struct, array $expectedResult): void
    {
        $fileCollection = new SnippetFileCollection();
        $converter = new SnippetFileConverter(
            $fileCollection,
            $this->getContainer()->get(SnippetFlattener::class)
        );

        $snippetFile = new SnippetFile_de_DE();
        $fileCollection->add($snippetFile);

        $snippetFile = new SnippetFile_en_GB();
        $fileCollection->add($snippetFile);

        $result = $converter->convert($struct);

        static::assertIsArray($result);
        $this->silentAssertArraySubset($expectedResult, $result);
    }

    public function dataProviderForTestConvert(): array
    {
        $snippetSet_UNK = new SnippetSetEntity();
        $snippetSet_UNK->setIso('');

        $snippetSet_EN = new SnippetSetEntity();
        $snippetSet_EN->setIso('en_GB');

        $snippetSet_DE = new SnippetSetEntity();
        $snippetSet_DE->setIso('de_DE');

        return [
            [$snippetSet_UNK, []],
            [$snippetSet_DE, ['frontend.note.item.ListingBoxLinkCompare' => 'Vergleichen']],
            [$snippetSet_DE, ['test.snippetName' => 'aaaaa']],
            [$snippetSet_EN, ['frontend.note.item.ListingBoxLinkCompare' => 'Compare']],
            [$snippetSet_EN, ['test.anotherSnippetName' => 'bbbbb']],
        ];
    }

    /**
     * @param SnippetFileInterface $languageFile
     * @param array                $expectedResult
     *
     * @dataProvider dataProviderForTestGetFileContent
     */
    public function testGetFileContent(SnippetFileInterface $languageFile, $expectedResult): void
    {
        $converter = new SnippetFileConverter(
        new SnippetFileCollection(),
        $this->getContainer()->get(SnippetFlattener::class)
    );
        $method = ReflectionHelper::getMethod(SnippetFileConverter::class, 'getFileContent');

        $result = $method->invoke($converter, $languageFile);

        $this->silentAssertArraySubset($expectedResult, $result);
    }

    public function dataProviderForTestGetFileContent(): array
    {
        return [
            [new SnippetFileMock(__DIR__ . '/_fixtures/contentFile_1.json'), []],
            [new SnippetFileMock(__DIR__ . '/_fixtures/contentFile_2.json'), []],
            [new SnippetFileMock(__DIR__ . '/_fixtures/contentFile_3.json'), ['index' => []]],
            [new SnippetFileMock(__DIR__ . '/_fixtures/testLanguage.json'), ['frontend' => ['AccountLoginTitle' => 'Login']]],
        ];
    }
}

class SnippetFileMock implements SnippetFileInterface
{
    public $path;
    public $name;
    public $iso;
    public $author;
    public $isBase;

    public function __construct(
        string $path = __DIR__ . '/_fixtures/test_three.json',
        string $name = 'SnippetFileMock',
        string $iso = 'en_GB',
        string $author = 'tests/shopware',
        bool $isBase = true
    ) {
        $this->path = $path;
        $this->name = $name;
        $this->iso = $iso;
        $this->author = $author;
        $this->isBase = $isBase;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getIso(): string
    {
        return $this->iso;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function isBase(): bool
    {
        return $this->isBase;
    }
}
