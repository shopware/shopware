<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\Services;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Snippet\Aggregate\SnippetSet\SnippetSetEntity;
use Shopware\Core\Framework\Snippet\Files\SnippetFileCollection;
use Shopware\Core\Framework\Snippet\Files\SnippetFileInterface;
use Shopware\Core\Framework\Snippet\Services\SnippetFileConverter;
use Shopware\Core\Framework\Snippet\Services\SnippetFlattener;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;

class SnippetFileConverterTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @param SnippetSetEntity $struct
     *
     * @dataProvider dataProviderForTestConvert
     */
    public function testConvert(SnippetSetEntity $struct, array $expectedResult): void
    {
        $converter = $this->getConverter();

        $result = $converter->convert($struct);

        static::assertInternalType('array', $result);
        static::assertArraySubset($expectedResult, $result);
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
            [$snippetSet_DE, ['widgets.emotion.components.component_article.ListingBoxNoPicture' => 'No image']],
            [$snippetSet_DE, ['widgets.emotion.components.component_blog.EmotionBlogPreviewNopic' => 'Kein Bild vorhanden']],
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
        $converter = $this->getConverter();
        $method = ReflectionHelper::getMethod(SnippetFileConverter::class, 'getFileContent');

        $result = $method->invoke($converter, $languageFile);

        static::assertArraySubset($expectedResult, $result);
    }

    public function dataProviderForTestGetFileContent(): array
    {
        return [
            [new SnippetFileMock(__DIR__ . '/../_fixtures/contentFile_1.json'), []],
            [new SnippetFileMock(__DIR__ . '/../_fixtures/contentFile_2.json'), []],
            [new SnippetFileMock(__DIR__ . '/../_fixtures/contentFile_3.json'), ['index' => []]],
            [new SnippetFileMock(__DIR__ . '/../_fixtures/testLanguage.json'), ['frontend' => ['AccountLoginTitle' => 'Login']]],
        ];
    }

    private function getConverter(): SnippetFileConverter
    {
        return new SnippetFileConverter(
            $this->getContainer()->get(SnippetFileCollection::class),
            $this->getContainer()->get(SnippetFlattener::class)
        );
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
        string $path = __DIR__ . '/../_fixtures/test_three.json',
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
