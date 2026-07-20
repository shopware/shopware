<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchTokenizer;

/**
 * @internal
 */
#[CoversClass(ElasticsearchTokenizer::class)]
class ElasticsearchTokenizerTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('tokenizeCases')]
    public function testTokenize(string $term, ?int $minLength, array $expected): void
    {
        $tokenizer = new ElasticsearchTokenizer();

        static::assertSame($expected, $tokenizer->tokenize($term, $minLength));
    }

    /**
     * @return iterable<string, array{string, ?int, list<string>}>
     */
    public static function tokenizeCases(): iterable
    {
        yield 'whitespace split, lowercased' => [
            'Bohrcraft DIN340',
            null,
            ['bohrcraft', 'din340'],
        ];

        yield 'comma is preserved (analyzer chain handles it)' => [
            'Bohrcraft 5,5',
            null,
            ['bohrcraft', '5,5'],
        ];

        yield 'slash and hyphen preserved on the search side' => [
            'M8x20 HSS-G size/M',
            null,
            ['m8x20', 'hss-g', 'size/m'],
        ];

        yield 'pure punctuation is rejected' => [
            '&%$',
            null,
            [],
        ];

        yield 'mixed punctuation and alphanumerics keeps the alnum-bearing token only' => [
            'foo --- bar',
            null,
            ['foo', 'bar'],
        ];

        yield 'tokens shorter than min length are dropped' => [
            'a bb ccc',
            2,
            ['bb', 'ccc'],
        ];

        yield 'duplicates are collapsed' => [
            'foo Foo FOO bar',
            null,
            ['foo', 'bar'],
        ];

        yield 'empty input yields empty list' => [
            '   ',
            null,
            [],
        ];

        yield 'unicode letters survive' => [
            'Größe 10',
            null,
            ['größe', '10'],
        ];

        yield 'default min length matches AbstractTokenFilter default (2)' => [
            'a 10',
            null,
            ['10'],
        ];
    }
}
