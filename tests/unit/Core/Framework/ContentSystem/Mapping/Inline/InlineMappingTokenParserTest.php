<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping\Inline;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingToken;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopware\Core\Framework\Log\Package;

/**
 * The token syntax, tested here because this class is its only definition — the render path and the write gate both
 * read it, and a divergence between what one admits and the other resolves is a security defect rather than a bug.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(InlineMappingTokenParser::class)]
class InlineMappingTokenParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function tokenTextProvider(): iterable
    {
        yield 'a plain token' => ['{{map:product.name}}', ['product.name']];
        yield 'inner whitespace on both sides' => ['{{ map:product.name }}', ['product.name']];
        yield 'a deep path' => ['{{map:product.manufacturer.name}}', ['product.manufacturer.name']];
        yield 'underscores and digits' => ['{{map:product.custom_field_2}}', ['product.custom_field_2']];
        yield 'two tokens in one string' => [
            '{{map:product.name}} costs {{map:product.price}}',
            ['product.name', 'product.price'],
        ];
        yield 'a token surrounded by prose and markup' => [
            '<p>Buy <b>{{map:product.name}}</b> now</p>',
            ['product.name'],
        ];

        // Each of these is deliberately NOT a token, and the reasons differ.
        yield 'no prefix, so it is an ordinary placeholder' => ['{{product.name}}', []];
        yield 'no dot, so it is not a mapping' => ['{{map:product}}', []];
        yield 'a single brace' => ['{map:product.name}', []];
        yield 'a hyphen is outside the path character class' => ['{{map:product.na-me}}', []];
        yield 'whitespace inside the path' => ['{{map:product. name}}', []];
        yield 'prefix casing is significant' => ['{{MAP:product.name}}', []];
        yield 'plain prose' => ['Buy it now', []];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('tokenTextProvider')]
    public function testParseFindsTheTokensAndOnlyTheTokens(string $text, array $expected): void
    {
        $paths = array_map(
            static fn (InlineMappingToken $token): string => $token->path,
            $this->parser()->parse($text)
        );

        static::assertSame($expected, $paths);
    }

    #[TestDox('containsToken is a cheap pre-filter, so it may say yes where parse finds nothing but never the reverse')]
    public function testContainsTokenNeverMissesAStringParseWouldMatch(): void
    {
        $parser = $this->parser();

        foreach (self::tokenTextProvider() as [$text, $expected]) {
            if ($expected === []) {
                continue;
            }

            static::assertTrue($parser->containsToken($text), $text);
        }
    }

    /**
     * The match is kept verbatim rather than rebuilt from the path, because leaving an uncatalogued token exactly as
     * typed is the documented fallback.
     */
    #[TestDox('carries the authored spelling of the token, whitespace included')]
    public function testTokenCarriesItsVerbatimMatchAndOffset(): void
    {
        $tokens = $this->parser()->parse('Buy {{ map:product.name }} today');

        static::assertCount(1, $tokens);
        static::assertSame('product.name', $tokens[0]->path);
        static::assertSame('{{ map:product.name }}', $tokens[0]->match);
        static::assertSame(4, $tokens[0]->offset);
    }

    public function testReplaceSubstitutesEveryResolvedToken(): void
    {
        $replaced = $this->parser()->replace(
            '{{map:product.name}} costs {{map:product.price}}',
            static fn (string $path): string => $path === 'product.name' ? 'Shirt' : '9.99'
        );

        static::assertSame('Shirt costs 9.99', $replaced);
    }

    #[TestDox('leaves a token verbatim when the resolver answers null, so an unknown one stays visible')]
    public function testReplaceLeavesATokenVerbatimWhenTheResolverAnswersNull(): void
    {
        $replaced = $this->parser()->replace(
            'Buy {{ map:product.unknown }} today',
            static fn (): ?string => null
        );

        static::assertSame('Buy {{ map:product.unknown }} today', $replaced);
    }

    #[TestDox('replaces with an empty string when the resolver answers one, which is the resolved-to-nothing case')]
    public function testReplaceHonoursAnEmptyStringAsAReplacement(): void
    {
        $replaced = $this->parser()->replace('Name: {{map:product.name}}.', static fn (): string => '');

        static::assertSame('Name: .', $replaced);
    }

    #[TestDox('does not re-scan what a replacement produced, so a token in a mapped value is not resolved')]
    public function testReplaceDoesNotRecurseIntoItsOwnOutput(): void
    {
        $replaced = $this->parser()->replace(
            '{{map:product.name}}',
            static fn (): string => '{{map:product.price}}'
        );

        static::assertSame('{{map:product.price}}', $replaced);
    }

    public function testCanonicalTokenCarriesThePrefixAndNoWhitespace(): void
    {
        static::assertSame('{{map:product.name}}', $this->parser()->canonicalToken('product.name'));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function markupPositionProvider(): iterable
    {
        yield 'in text content' => ['<p>{{map:product.name}}</p>', false];
        yield 'in an attribute value' => ['<a href="/{{map:product.name}}">x</a>', true];
        yield 'in an attribute of a tag following a closed one' => ['<p>hi</p><a title="{{map:product.name}}">x</a>', true];
        yield 'after a closed tag' => ['<p>hi</p>{{map:product.name}}', false];
        yield 'no markup at all' => ['{{map:product.name}}', false];
        yield 'after a stray closing bracket' => ['a > b {{map:product.name}}', false];
    }

    #[DataProvider('markupPositionProvider')]
    #[TestDox('detects whether a token sits inside an HTML tag')]
    public function testOccursInsideMarkup(string $text, bool $expected): void
    {
        $parser = $this->parser();
        $tokens = $parser->parse($text);

        static::assertCount(1, $tokens);
        static::assertSame($expected, $parser->occursInsideMarkup($text, $tokens[0]->offset));
    }

    private function parser(): InlineMappingTokenParser
    {
        return new InlineMappingTokenParser();
    }
}
