<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SearchKeyword;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchKeywordAnalyzer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;
use Shopware\Core\System\Tag\TagCollection;
use Shopware\Core\System\Tag\TagEntity;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\SearchKeyword\ProductSearchKeywordAnalyzer
 */
class ProductSearchKeywordAnalyzerTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    /**
     * @dataProvider analyzeCases
     *
     * @param array<string, mixed> $productData
     * @param array<int, array{field: string, tokenize: bool, ranking: int}> $configFields
     * @param array<int, string> $expected
     */
    public function testAnalyze(array $productData, array $configFields, array $expected): void
    {
        $product = new ProductEntity();
        $product->assign($productData);

        $tokenizer = new Tokenizer(3, ['-', '_']);
        $tokenFilter = $this->createMock(TokenFilter::class);
        $tokenFilter->method('filter')->willReturnCallback(fn (array $tokens) => $tokens);

        $analyzer = new ProductSearchKeywordAnalyzer($tokenizer, $tokenFilter);
        $analyzer = $analyzer->analyze($product, $this->context, $configFields);
        $analyzerResult = $analyzer->getKeys();

        sort($analyzerResult);
        sort($expected);

        static::assertEquals($expected, $analyzerResult);
    }

    /**
     * The old implementation relied on the error_reporting level, to also report notices as errors.
     * This test ensures that the new implementation does not rely on the error_reporting level.
     *
     * @dataProvider analyzeCases
     *
     * @param array<string, mixed> $productData
     * @param array<int, array{field: string, tokenize: bool, ranking: int}> $configFields
     * @param array<int, string> $expected
     */
    public function testAnalyzeWithIgnoredErrorNoticeReporting(array $productData, array $configFields, array $expected): void
    {
        $oldLevel = error_reporting(\E_ERROR);

        $this->testAnalyze($productData, $configFields, $expected);

        error_reporting($oldLevel);
    }

    /**
     * @return iterable<string, array{0:array<string, array<string, string|array<int|string, string|array<int|string>>>|int|string|TagCollection>, 1:array<int, array{field: string, tokenize: bool, ranking: int}>, 2:array<int, int|string>}>
     */
    public static function analyzeCases(): iterable
    {
        $tag1 = new TagEntity();
        $tag1->setId('tag-1');
        $tag1->setName('Tag Yellow');

        $tag2 = new TagEntity();
        $tag2->setId('tag-2');
        $tag2->setName('Tag Pink');

        $tags = new TagCollection([$tag1, $tag2]);

        yield 'analyze with tokenize' => [
            [
                'maxPurchase' => 20,
                'manufacturerNumber' => 'MANU_001',
                'description' => self::getLongTextDescription(),
                'tags' => $tags,
                'translated' => [
                    'name' => 'Awesome product',
                ],
            ],
            [
                [
                    'field' => 'maxPurchase',
                    'tokenize' => true,
                    'ranking' => 100,
                ],
                [
                    'field' => 'description',
                    'tokenize' => true,
                    'ranking' => 100,
                ],
                [
                    'field' => 'tags.name',
                    'tokenize' => true,
                    'ranking' => 100,
                ],
                [
                    'field' => 'manufacturerNumber',
                    'tokenize' => true,
                    'ranking' => 100,
                ],
                [
                    'field' => 'name',
                    'tokenize' => true,
                    'ranking' => 100,
                ],
            ],
            [
                20,
                'awesome',
                'description',
                'long',
                'manu_001',
                'pink',
                'product',
                'tag',
                'this',
                'yellow',
            ],
        ];

        yield 'analyze without tokenize' => [
            [
                'maxPurchase' => 20,
                'manufacturerNumber' => 'MANU_001',
                'description' => self::getLongTextDescription(),
                'tags' => $tags,
                'translated' => [
                    'name' => 'Awesome product',
                ],
            ],
            [
                [
                    'field' => 'maxPurchase',
                    'tokenize' => false,
                    'ranking' => 100,
                ],
                [
                    'field' => 'description',
                    'tokenize' => false,
                    'ranking' => 100,
                ],
                [
                    'field' => 'tags.name',
                    'tokenize' => false,
                    'ranking' => 100,
                ],
                [
                    'field' => 'manufacturerNumber',
                    'tokenize' => false,
                    'ranking' => 100,
                ],
                [
                    'field' => 'name',
                    'tokenize' => true,
                    'ranking' => 100,
                ],
            ],
            [
                20,
                'MANU_001',
                'Tag Pink',
                'Tag Yellow',
                self::getLongTextPart1(),
                self::getLongTextPart2(),
                'awesome',
                'product',
            ],
        ];

        yield 'analyze nested array field' => [
            [
                'customFields' => [
                    'flat' => [
                        'part-a', 'part-b',
                    ],
                    'nested' => [
                        'part-a' => ['a1', 'a2'], 'part-b' => ['b1', 'b2'],
                    ],
                    'nested-with-long-desc' => [
                        'part-a' => [self::getLongTextDescription()],
                    ],
                ],
                'translated' => [
                    'name' => 'Awesome product',
                ],
            ],
            [
                [
                    'field' => 'customFields.flat',
                    'tokenize' => true,
                    'ranking' => 100,
                ],
                [
                    'field' => 'customFields.nested',
                    'tokenize' => true,
                    'ranking' => 100,
                ],
                [
                    'field' => 'nested-with-long-desc',
                    'tokenize' => false,
                    'ranking' => 100,
                ],
                [
                    'field' => 'name',
                    'tokenize' => true,
                    'ranking' => 100,
                ],
            ],
            [
                'awesome',
                'part-a',
                'part-b',
                'product',
            ],
        ];
    }

    private static function getLongTextDescription(): string
    {
        return self::getLongTextPart1() . self::getLongTextPart2();
    }

    private static function getLongTextPart1(): string
    {
        return 'This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long descripti';
    }

    private static function getLongTextPart2(): string
    {
        return 'on. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description.';
    }
}
