<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping\Inline;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingExpansionBudget;
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
    public function testPathlessTypedSourceIsMalformedMappingIntent(): void
    {
        $tokens = $this->parser()->parse('{{map:context:example}}');
        static::assertCount(1, $tokens);
        static::assertSame('context:example', $tokens[0]->path);
        static::assertTrue($tokens[0]->malformed);
    }

    public function testEditorResponseFixturesMatchTheServerParser(): void
    {
        $file = \dirname(__DIR__, 7) . '/src/Administration/Resources/app/administration/src/module/sw-experience-studio/component/sw-experience-studio-inline-text-field/fixtures/parser-responses.json';
        $contents = file_get_contents($file);
        static::assertNotFalse($contents);
        $fixtures = json_decode($contents, true, flags: \JSON_THROW_ON_ERROR);
        foreach ($fixtures as $fixture) {
            $actual = [];
            foreach ($this->parser()->parse($fixture['text']) as $token) {
                $actual[] = [
                    'path' => $token->path, 'match' => $token->match,
                    'canonicalToken' => $token->malformed ? null : $this->parser()->canonicalToken($token->path),
                    'offset' => $token->offset, 'insideMarkup' => $token->insideMarkup, 'malformed' => $token->malformed,
                ];
            }
            static::assertSame($fixture['tokens'], $actual, $fixture['text']);
        }
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function tokenTextProvider(): iterable
    {
        yield 'a plain token' => ['{{map:product.name}}', ['product.name']];
        yield 'inner whitespace on both sides' => ['{{ map:product.name }}', ['product.name']];
        yield 'a deep path' => ['{{map:product.manufacturer.name}}', ['product.manufacturer.name']];
        yield 'a typed plugin source' => ['{{map:plugin:example.message}}', ['plugin:example.message']];
        yield 'a namespaced typed source' => ['{{map:external-source:lookup-id.value}}', ['external-source:lookup-id.value']];
        yield 'underscores and digits' => ['{{map:product.custom_field_2}}', ['product.custom_field_2']];
        yield 'a Unicode custom field name' => ['{{map:product.customFields.größe}}', ['product.customFields.größe']];
        yield 'two tokens in one string' => [
            '{{map:product.name}} costs {{map:product.price}}',
            ['product.name', 'product.price'],
        ];
        yield 'a token surrounded by prose and markup' => [
            '<p>Buy <b>{{map:product.name}}</b> now</p>',
            ['product.name'],
        ];

        // A marker with an invalid path is still recognized so diagnostics can report it and rendering can remove it.
        yield 'no prefix, so it is an ordinary placeholder' => ['{{product.name}}', []];
        yield 'ordinary placeholder with inner spaces' => ['{{ product.name }}', []];
        yield 'an unclosed marker is an ordinary no-match result' => ['{{map:product.name', []];
        yield 'no member path is malformed mapping intent' => ['{{map:product}}', ['product']];
        yield 'empty path is malformed mapping intent' => ['{{map:}}', ['']];
        yield 'a single brace' => ['{map:product.name}', []];
        yield 'a hyphen is outside the path character class' => ['{{map:product.na-me}}', ['product.na-me']];
        yield 'whitespace inside the path is malformed mapping intent' => ['{{map:product. name}}', ['product. name']];
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

    #[DataProvider('reservedPlaceholderKeys')]
    public function testReservedPlaceholderKeysAgreeWithTokenRecognition(string $key, bool $reserved): void
    {
        static::assertSame($reserved, InlineMappingTokenParser::isReservedPlaceholderKey($key));
        static::assertCount($reserved ? 1 : 0, $this->parser()->parse('{{' . $key . '}}'));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function reservedPlaceholderKeys(): iterable
    {
        yield 'canonical mapping' => ['map:product.name', true];
        yield 'space' => [' map:product.name ', true];
        yield 'tab' => ["\tmap:product.name", true];
        yield 'newline' => ["\nmap:product.name", true];
        yield 'carriage return' => ["\rmap:product.name", true];
        yield 'vertical tab' => ["\vmap:product.name", true];
        yield 'form feed' => ["\fmap:product.name", true];
        yield 'mixed whitespace' => [" \t\n\r\v\fmap:product.name", true];
        yield 'empty mapping path' => ['map:', true];
        yield 'mapping without a member' => ['map:product', true];
        yield 'ordinary key' => ['productId', false];
        yield 'ordinary colon key' => ['external:id', false];
        yield 'uppercase prefix' => ['MAP:product.name', false];
        yield 'similar prefix' => ['mapping:id', false];
        yield 'space within prefix' => ['map :product.name', false];
        yield 'prefix within key' => ['xmap:product.name', false];
        yield 'leading null byte' => ["\0map:product.name", false];
    }

    public function testReservedPlaceholderCheckDoesNotApplyTheAuthoredInputLimit(): void
    {
        $key = str_repeat(' ', InlineMappingTokenParser::MAX_INPUT_BYTES + 1) . 'map:product.name';

        static::assertTrue(InlineMappingTokenParser::isReservedPlaceholderKey($key));
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
        static::assertFalse($tokens[0]->malformed);
    }

    public function testMarksAMappingMarkerWithNoMemberAsMalformed(): void
    {
        $tokens = $this->parser()->parse('Buy {{map:product}} today');

        static::assertCount(1, $tokens);
        static::assertSame('product', $tokens[0]->path);
        static::assertTrue($tokens[0]->malformed);
    }

    public function testParseRejectsOversizedTextThatContainsMappingIntent(): void
    {
        $text = str_repeat('x', InlineMappingTokenParser::MAX_INPUT_BYTES) . '{{map:product.name}}';

        $this->expectExceptionObject(ContentSystemException::inlineMappingInputTooLarge(InlineMappingTokenParser::MAX_INPUT_BYTES));

        $this->parser()->parse($text);
    }

    public function testParseAllowsLongLiteralTextWithoutMappingIntent(): void
    {
        $text = str_repeat('x', InlineMappingTokenParser::MAX_INPUT_BYTES + 1);

        static::assertSame([], $this->parser()->parse($text));
    }

    #[DataProvider('scanFailurePrefixes')]
    public function testParseThrowsInsteadOfReturningAnIncompleteScan(string $prefix): void
    {
        $previousLimit = ini_get('pcre.backtrack_limit');
        static::assertNotFalse($previousLimit);
        static::assertNotFalse(ini_set('pcre.backtrack_limit', '100'));

        try {
            $this->expectExceptionObject(ContentSystemException::inlineMappingParsingFailed('scan tokens in', 'Backtrack limit exhausted'));

            $this->parser()->parse($prefix . '{{map:' . str_repeat('x', 500) . '}}');
        } finally {
            static::assertNotFalse(ini_set('pcre.backtrack_limit', $previousLimit));
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function scanFailurePrefixes(): iterable
    {
        yield 'failure before the first token cannot look like an empty scan' => [''];
        yield 'failure after a token cannot return a partial scan' => ['{{map:product.name}} '];
    }

    public function testPathValidationThrowsInsteadOfReportingAMalformedPathOnEngineFailure(): void
    {
        $path = 'product.' . str_repeat('name.', 500) . 'name';
        $previousLimit = ini_get('pcre.backtrack_limit');
        static::assertNotFalse($previousLimit);
        static::assertNotFalse(ini_set('pcre.backtrack_limit', '100'));

        try {
            $this->expectExceptionObject(ContentSystemException::inlineMappingParsingFailed('validate a path in', 'Backtrack limit exhausted'));

            InlineMappingTokenParser::isValidPath($path);
        } finally {
            static::assertNotFalse(ini_set('pcre.backtrack_limit', $previousLimit));
        }
    }

    public function testParseScansMoreThanTheOldTokenLimitWithoutTruncating(): void
    {
        $tokenCount = 1_000;
        $text = implode(' ', array_fill(0, $tokenCount, '{{map:product.name}}'));

        static::assertCount($tokenCount, $this->parser()->parse($text));
    }

    public function testReplaceTokensSubstitutesEveryResolvedToken(): void
    {
        $parser = $this->parser();
        $text = '{{map:product.name}} costs {{map:product.price}}';
        $tokens = $parser->parse($text);

        $replaced = $parser->replaceTokens(
            $text,
            $tokens,
            static fn (string $path): string => $path === 'product.name' ? 'Shirt' : '9.99',
            budget: new InlineMappingExpansionBudget()
        );

        static::assertSame('Shirt costs 9.99', $replaced);
    }

    #[TestDox('leaves a token verbatim when the resolver answers null, so an unknown one stays visible')]
    public function testReplaceTokensLeavesATokenVerbatimWhenTheResolverAnswersNull(): void
    {
        $parser = $this->parser();
        $text = 'Buy {{ map:product.unknown }} today';
        $tokens = $parser->parse($text);

        $replaced = $parser->replaceTokens(
            $text,
            $tokens,
            static fn (): ?string => null,
            budget: new InlineMappingExpansionBudget()
        );

        static::assertSame('Buy {{ map:product.unknown }} today', $replaced);
    }

    public function testReplaceTokensRemovesMalformedMappingIntentWithoutCallingTheResolver(): void
    {
        $parser = $this->parser();
        $text = 'Buy {{map:product}} today';
        $tokens = $parser->parse($text);
        $resolverCalled = false;
        $resolve = static function () use (&$resolverCalled): ?string {
            $resolverCalled = true;

            return null;
        };

        static::assertSame('Buy  today', $parser->replaceTokens($text, $tokens, $resolve, budget: new InlineMappingExpansionBudget()));
        static::assertFalse($resolverCalled);
    }

    public function testReplaceTokensRemovesMalformedMappingIntentInsideMarkup(): void
    {
        $parser = $this->parser();
        $text = '<a href="{{map:product}}">link</a>';
        $tokens = $parser->parse($text);

        static::assertSame(
            '<a href="">link</a>',
            $parser->replaceTokens($text, $tokens, static fn (): string => 'unused', budget: new InlineMappingExpansionBudget())
        );
    }

    #[TestDox('replaces with an empty string when the resolver answers one, which is the resolved-to-nothing case')]
    public function testReplaceTokensHonoursAnEmptyStringAsAReplacement(): void
    {
        $parser = $this->parser();
        $text = 'Name: {{map:product.name}}.';

        $replaced = $parser->replaceTokens($text, $parser->parse($text), static fn (): string => '', budget: new InlineMappingExpansionBudget());

        static::assertSame('Name: .', $replaced);
    }

    #[TestDox('does not re-scan what a replacement produced, so a token in a mapped value is not resolved')]
    public function testReplaceTokensDoesNotRecurseIntoItsOwnOutput(): void
    {
        $parser = $this->parser();
        $text = '{{map:product.name}}';

        $replaced = $parser->replaceTokens(
            $text,
            $parser->parse($text),
            static fn (): string => '{{map:product.price}}',
            budget: new InlineMappingExpansionBudget()
        );

        static::assertSame('{{map:product.price}}', $replaced);
    }

    #[TestDox('does not parse token-like text introduced after authored spans were captured')]
    public function testReplaceTokensOnlyUsesPreparedSpans(): void
    {
        $parser = $this->parser();
        $authored = 'Buy {{map:product.name}} for {{price}}';
        $tokens = $parser->parse($authored);
        $prepared = 'Buy {{map:product.name}} for {{map:product.price}}';

        $replaced = $parser->replaceTokens($prepared, $tokens, static fn (): string => 'Shirt', budget: new InlineMappingExpansionBudget());

        static::assertSame('Buy Shirt for {{map:product.price}}', $replaced);
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
        yield 'quoted greater-than inside an attribute' => ['<a title="a > {{map:product.name}}">x</a>', true];
    }

    #[DataProvider('markupPositionProvider')]
    #[TestDox('detects whether a token sits inside an HTML tag')]
    public function testParseMarksTokensInsideMarkup(string $text, bool $expected): void
    {
        $tokens = $this->parser()->parse($text);

        static::assertCount(1, $tokens);
        static::assertSame($expected, $tokens[0]->insideMarkup);
    }

    #[TestDox('never resolves a mapping token inside an HTML attribute')]
    public function testReplaceTokensLeavesAttributeTokensLiteral(): void
    {
        $parser = $this->parser();
        $text = '<a title="a > {{map:product.name}}">{{map:product.name}}</a>';
        $tokens = $parser->parse($text);

        $resolved = $parser->replaceTokens($text, $tokens, static fn (): string => 'Shirt', budget: new InlineMappingExpansionBudget());

        static::assertSame('<a title="a > {{map:product.name}}">Shirt</a>', $resolved);
    }

    public function testCountsLiteralTextAfterTheLastReplacementAgainstTheBudget(): void
    {
        $parser = $this->parser();
        $text = '{{map:product.name}}</p>';
        $budget = new InlineMappingExpansionBudget();
        $budget->consume(1048576 - 5);

        $this->expectExceptionObject(ContentSystemException::inlineMappingExpansionTooLarge(1048576, 'property'));

        $parser->replaceTokens($text, $parser->parse($text), static fn (): string => 'xx', $budget);
    }

    public function testStopsResolvingOnceThePropertyAllowanceIsConsumed(): void
    {
        $parser = $this->parser();
        $text = '{{map:product.name}}{{map:product.name}}';
        $calls = 0;
        $resolve = static function () use (&$calls): string {
            ++$calls;

            return str_repeat('x', 1048576);
        };

        $this->expectExceptionObject(ContentSystemException::inlineMappingExpansionTooLarge(1048576, 'property'));

        try {
            $parser->replaceTokens($text, $parser->parse($text), $resolve, new InlineMappingExpansionBudget());
        } finally {
            static::assertSame(1, $calls);
        }
    }

    public function testEmptyTokenListPreservesLiteralTextWithoutCallingTheResolver(): void
    {
        $text = '🙂 Literal {{ordinary}} text';
        $budget = new InlineMappingExpansionBudget();
        $resolve = static function (): never {
            static::fail('No source should be resolved without token spans.');
        };

        static::assertSame($text, $this->parser()->replaceTokens($text, [], $resolve, $budget));
        $budget->consume(InlineMappingExpansionBudget::MAX_PROPERTY_BYTES - \strlen($text));
        $this->expectExceptionObject(ContentSystemException::inlineMappingExpansionTooLarge(InlineMappingExpansionBudget::MAX_PROPERTY_BYTES, 'property'));

        $budget->consume(1);
    }

    private function parser(): InlineMappingTokenParser
    {
        return new InlineMappingTokenParser();
    }
}
