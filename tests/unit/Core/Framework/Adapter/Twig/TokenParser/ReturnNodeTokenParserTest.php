<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\TokenParser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\TokenParser\ReturnNodeTokenParser;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Parser;
use Twig\Source;
use Twig\Token;
use Twig\TokenStream;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ReturnNodeTokenParser::class)]
class ReturnNodeTokenParserTest extends TestCase
{
    /**
     * @param list<string> $whitespace
     */
    #[DataProvider('whitespaceProvider')]
    public function testSkipsTrailingWhitespace(array $whitespace): void
    {
        $next = new Token(Token::BLOCK_START_TYPE, '', 3);
        $stream = new TokenStream([
            new Token(Token::BLOCK_END_TYPE, '', 1),
            ...array_map(static fn (string $text) => new Token(Token::TEXT_TYPE, $text, 2), $whitespace),
            $next,
            new Token(Token::EOF_TYPE, '', 3),
        ], new Source('', 'test.twig'));
        $parser = static::createStub(Parser::class);
        $parser->method('getStream')->willReturn($stream);
        $returnParser = new ReturnNodeTokenParser();
        $returnParser->setParser($parser);

        $node = $returnParser->parse(new Token(Token::NAME_TYPE, 'return', 1));

        static::assertSame($next, $stream->getCurrent());
        static::assertFalse($node->hasNode('expr'));
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function whitespaceProvider(): iterable
    {
        yield 'no text after return' => [[]];
        yield 'indentation after return' => [['    ']];
        yield 'newlines and tabs after return' => [["\r\n\t \n"]];
        yield 'consecutive whitespace tokens' => [["\n", '   ']];
    }

    #[DataProvider('textProvider')]
    public function testPreservesFollowingMeaningfulText(string $text): void
    {
        $next = new Token(Token::TEXT_TYPE, $text, 2);
        $stream = new TokenStream([
            new Token(Token::BLOCK_END_TYPE, '', 1),
            new Token(Token::TEXT_TYPE, "\n    ", 2),
            $next,
            new Token(Token::EOF_TYPE, '', 3),
        ], new Source('', 'test.twig'));
        $parser = static::createStub(Parser::class);
        $parser->method('getStream')->willReturn($stream);
        $returnParser = new ReturnNodeTokenParser();
        $returnParser->setParser($parser);

        $returnParser->parse(new Token(Token::NAME_TYPE, 'return', 1));

        static::assertSame($next, $stream->getCurrent());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function textProvider(): iterable
    {
        yield 'HTML remains available for unreachable-code analysis' => ['<p>unreachable</p>'];
        yield 'whitespace attached to meaningful text is preserved' => ["\n    text"];
        yield 'zero is meaningful text' => ['0'];
    }

    #[DataProvider('conditionalReturnProvider')]
    public function testConditionalReturnPreservesOtherBranch(bool $stop, string $expected): void
    {
        $twig = new Environment(new ArrayLoader([
            'test.twig' => "before{% if stop %}{% return %} \n    {% else %}else{% endif %}after",
        ]), ['strict_variables' => true]);
        $twig->addTokenParser(new ReturnNodeTokenParser());

        static::assertSame($expected, $twig->render('test.twig', ['stop' => $stop]));
    }

    /**
     * @return iterable<string, array{bool, string}>
     */
    public static function conditionalReturnProvider(): iterable
    {
        yield 'return stops rendering' => [true, 'before'];
        yield 'else branch still renders' => [false, 'beforeelseafter'];
    }

    public function testParsesReturnValue(): void
    {
        $twig = new Environment(new ArrayLoader(['test.twig' => '{% return 42 %}   ']));
        $twig->addTokenParser(new ReturnNodeTokenParser());

        $compiled = $twig->compileSource(new Source('{% return 42 %}   ', 'test.twig'));

        static::assertStringContainsString('returnFromMacro(42);', $compiled);
    }
}
