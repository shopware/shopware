<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SearchKeyword;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\SearchKeyword\KeywordLoader;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;

/**
 * @internal
 */
#[CoversClass(ProductSearchTermInterpreter::class)]
class ProductSearchTermInterpreterTest extends TestCase
{
    public function testReturnsEmptyPatternIfEmptyTerm(): void
    {
        $term = '';

        $interpreter = new ProductSearchTermInterpreter(
            static::createMock(Connection::class),
            new Tokenizer(3),
            static::createMock(LoggerInterface::class),
            new TokenFilter(static::createMock(Connection::class)),
            static::createMock(KeywordLoader::class),
        );

        $pattern = $interpreter->interpret($term, Context::createDefaultContext());

        static::assertEmpty($pattern->getTerms());
    }

    public function testReturnsEmptyPatternIfTokensToShort(): void
    {
        $term = 'a b c d';

        $interpreter = new ProductSearchTermInterpreter(
            static::createMock(Connection::class),
            static::createMock(Tokenizer::class),
            static::createMock(LoggerInterface::class),
            new TokenFilter(static::createMock(Connection::class)),
            static::createMock(KeywordLoader::class),
        );

        $pattern = $interpreter->interpret($term, Context::createDefaultContext());

        static::assertEmpty($pattern->getTerms());
    }

    public function testTokenEncodingsStayIntact(): void
    {
        $term = 'foo-äöüß-مرحب-bar';
        $keywordLoader = static::createMock(KeywordLoader::class);

        $keywordLoader->expects(static::once())->method('fetch')
            ->with(static::callback(function ($tokenSlops) use ($term) {
                $tokens = [
                    ...$tokenSlops[$term]['reversed'],
                    ...$tokenSlops[$term]['normal'],
                ];
                $encodings = [];

                foreach ($tokens as $token) {
                    $encodings[] = mb_detect_encoding($token, null, true);
                }

                static::assertNotContains(false, $encodings, 'At least one of the tokens is not properly encoded');

                return true;
            }));

        $interpreter = new ProductSearchTermInterpreter(
            static::createMock(Connection::class),
            new Tokenizer(3),
            static::createMock(LoggerInterface::class),
            new TokenFilter(static::createMock(Connection::class)),
            $keywordLoader,
        );

        $interpreter->interpret($term, Context::createDefaultContext());
    }

    public function testMaxCharacterCount(): void
    {
        $term = 'This is a very long search term that should be cut off at some point to prevent the keyword query from exploding';
        $keywordLoader = static::createMock(KeywordLoader::class);

        $keywordLoader->expects(static::once())->method('fetch')
            ->with(static::callback(function ($tokenSlops) {
                $tokens = array_keys($tokenSlops);
                $chars = implode('', $tokens);

                static::assertEquals(ProductSearchTermInterpreter::MAX_CHARACTER_COUNT, mb_strlen($chars));

                return true;
            }));

        $interpreter = new ProductSearchTermInterpreter(
            static::createMock(Connection::class),
            new Tokenizer(3),
            static::createMock(LoggerInterface::class),
            new TokenFilter(static::createMock(Connection::class)),
            $keywordLoader,
        );

        $interpreter->interpret($term, Context::createDefaultContext());
    }
}
