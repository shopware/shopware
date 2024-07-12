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

    public function testExactScoringMatches(): void
    {
        $term = 'Aerodynamic Aluminum Chambermaid Placemats';
        $keywordLoader = static::createMock(KeywordLoader::class);
        $keywordLoader->expects(static::once())->method('fetch')
            ->willReturnCallback(function ($tokenSlops) {
                return [
                    ['aerodynamic', '1', '0', '0', '0'],
                    ['alumimagic', '0', '1', '0', '0'],
                    ['aluminum', '0', '1', '0', '0'],
                    ['chambermaid', '0', '0', '1', '0'],
                    ['placemats', '0', '0', '0', '1'],
                ];
            });

        $interpreter = new ProductSearchTermInterpreter(
            $this->createMock(Connection::class),
            new Tokenizer(3),
            $this->createMock(LoggerInterface::class),
            new TokenFilter(static::createMock(Connection::class)),
            $keywordLoader,
        );

        $actualScoring = $interpreter->interpret($term, Context::createDefaultContext());

        static::assertSame($term, $actualScoring->getOriginal()->getTerm());
        static::assertSame(1.0, $actualScoring->getOriginal()->getScore());

        $expectedScoring = [
            'aerodynamic' => 1.1,
            'aluminum' => 1.1,
            'chambermaid' => 1.1,
            'placemats' => 1.1,
            'alumimagic' => 0.1,
        ];

        $actualScoringFlat = [];
        foreach ($actualScoring->getTerms() as $searchTerm) {
            $actualScoringFlat[$searchTerm->getTerm()] = $searchTerm->getScore();
        }

        static::assertSame($expectedScoring, $actualScoringFlat);
    }
}
