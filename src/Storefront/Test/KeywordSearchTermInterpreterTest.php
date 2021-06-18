<?php declare(strict_types=1);

namespace Shopware\Storefront\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreter;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreterInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTerm;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\ArrayNormalizer;
use Shopware\Core\Framework\Uuid\Uuid;

class KeywordSearchTermInterpreterTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ProductSearchTermInterpreterInterface
     */
    private $interpreter;

    /**
     * @var EntityRepositoryInterface
     */
    private $productSearchConfigRepository;

    /**
     * @var string
     */
    private $productSearchConfigId;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->interpreter = $this->getContainer()->get(ProductSearchTermInterpreter::class);

        $this->productSearchConfigRepository = $this->getContainer()->get('product_search_config.repository');
        $this->productSearchConfigId = $this->getProductSearchConfigId();

        $this->setupKeywords();
    }

    /**
     * @dataProvider cases
     */
    public function testMatching(string $term, array $expected): void
    {
        $context = Context::createDefaultContext();

        $matches = $this->interpreter->interpret($term, $context);

        $keywords = array_map(function (SearchTerm $term) {
            return $term->getTerm();
        }, $matches->getTerms());

        sort($expected);
        sort($keywords);
        static::assertEquals($expected, $keywords);
    }

    /**
     * @dataProvider casesWithTokenFilter
     */
    public function testMatchingWithTokenFilter(string $term, array $expected): void
    {
        $context = Context::createDefaultContext();

        $matches = $this->interpreter->interpret($term, $context);

        $keywords = array_map(function (SearchTerm $term) {
            return $term->getTerm();
        }, $matches->getTerms());

        sort($expected);
        sort($keywords);
        static::assertEquals($expected, $keywords);
    }

    /**
     * @dataProvider caseWithFetchingTokenTerms
     */
    public function testMatchingTokenTerms(string $term, array $expected): void
    {
        $context = Context::createDefaultContext();

        $matches = $this->interpreter->interpret($term, $context);

        $tokenTerms = $matches->getTokenTerms();

        static::assertEquals(\count($expected), \count($tokenTerms));
        foreach ($tokenTerms as $index => $tokenTerm) {
            sort($expected[$index]);
            sort($tokenTerm);

            static::assertEquals($expected[$index], $tokenTerm);
        }
    }

    /**
     * @dataProvider caseWithMatchingBooleanCause
     */
    public function testMatchingBooleanClause(bool $andLogic, string $expected): void
    {
        $context = Context::createDefaultContext();

        $this->productSearchConfigRepository->update([
            ['id' => $this->productSearchConfigId, 'andLogic' => $andLogic],
        ], $context);

        $matches = $this->interpreter->interpret('Random terms', $context);

        $booleanClause = $matches->getBooleanClause();

        static::assertEquals($expected, $booleanClause);
    }

    /**
     * @dataProvider caseWithMatchingSearchPatternTermLength
     */
    public function testMatchingSearchPatternTermLength(bool $andLogic, string $words): void
    {
        $context = Context::createDefaultContext();

        $this->productSearchConfigRepository->update([
            ['id' => $this->productSearchConfigId, 'andLogic' => $andLogic],
        ], $context);

        $matches = $this->interpreter->interpret($words, $context);
        $terms = array_map(function (SearchTerm $term) {
            return $term->getTerm();
        }, $matches->getTerms());

        if (!$andLogic) {
            $flatterTerms = ArrayNormalizer::flatten($matches->getTokenTerms());

            static::assertLessThanOrEqual(\count($flatterTerms), \count($terms));
            static::assertLessThanOrEqual(8, \count($terms));

            return;
        }

        static::assertGreaterThanOrEqual(0, \count($terms));
    }

    public function cases(): array
    {
        return [
            [
                'zeichn',
                ['zeichnet', 'zeichen', 'zweichnet'],
            ],
            [
                'zeichent',
                ['ausgezeichnet', 'gezeichnet', 'zeichnet'],
            ],
            [
                'Büronetz',
                ['büronetzwerk'],
            ],
            [
                '1000',
                ['10000', '10001', '10002', '10007'],
            ],
            [
                '10',
                ['10', '10000', '10001', '10002', '10007'],
            ],
        ];
    }

    public function casesWithTokenFilter(): array
    {
        return [
            [
                'zeichn',
                ['zeichnet', 'zeichen', 'zweichnet'],
            ],
            [
                'zeichent',
                ['ausgezeichnet', 'gezeichnet', 'zeichnet'],
            ],
            [
                'Büronetz',
                ['büronetzwerk'],
            ],
            [
                '1000',
                ['10000', '10001', '10002', '10007'],
            ],
            [
                '1',
                [],
            ],
            [
                'between against in on',
                [],
            ],
            [
                'between against on in coffee bike',
                ['betweencoffee', 'betweenbike'],
            ],
        ];
    }

    public function caseWithFetchingTokenTerms(): array
    {
        return [
            [
                'zeichn zeichent Büronetz',
                [
                    ['zeichnet', 'zeichen', 'zweichnet'],
                    ['ausgezeichnet', 'gezeichnet', 'zeichnet'],
                    ['büronetzwerk'],
                ],
            ],
            [
                'Büronetz 1000',
                [
                    ['büronetzwerk'],
                    ['10000', '10001', '10002', '10007'],
                ],
            ],
            [
                'Büronetz',
                [
                    ['büronetzwerk'],
                ],
            ],
            [
                'Büronetz 1',
                [
                    ['büronetzwerk'],
                ],
            ],
            [
                'against 1',
                [],
            ],
            [
                '2 1',
                [],
            ],
            [
                'zeichn zeichn',
                [
                    ['zeichnet', 'zeichen', 'zweichnet'],
                ],
            ],
            [
                '@##@$^zeichn$@#$#@ {}|=-!@#@!#zeichent[]-/\}{ ?"Büronetz?"',
                [
                    ['zeichnet', 'zeichen', 'zweichnet'],
                    ['ausgezeichnet', 'gezeichnet', 'zeichnet'],
                    ['büronetzwerk'],
                ],
            ],
            [
                '³²¼¼³¬½{¬]Büronetz³²¼¼³¬½{¬] ³²¼¼³¬½{¬]1000³²¼¼³¬½{¬]',
                [
                    ['büronetzwerk'],
                    ['10000', '10001', '10002', '10007'],
                ],
            ],
            [
                '¯\_(๑❛ᴗ❛๑)_/¯zeichn$¯\_(๑❛ᴗ❛๑)_/¯ ʚ(´◡`)zeichent(´◡`)ɞ ʚ(´◡`)Büronetz¯\_(๑❛ᴗ❛๑)_/¯',
                [
                    ['zeichnet', 'zeichen', 'zweichnet'],
                    ['ausgezeichnet', 'gezeichnet', 'zeichnet'],
                    ['büronetzwerk'],
                ],
            ],
            [
                '(๑★ .̫ ★๑)Büronet（★￣∀￣★） (̂ ˃̥̥̥ ˑ̫ ˂̥̥̥ )̂1000(*＾v＾*)',
                [
                    ['büronetzwerk'],
                    ['10000', '10001', '10002', '10007'],
                ],
            ],
            [
                '‰€€Büronet¥Æ ‡‡1000††',
                [
                    ['büronetzwerk'],
                    ['10000', '10001', '10002', '10007'],
                ],
            ],
        ];
    }

    public function caseWithMatchingBooleanCause(): array
    {
        return [
            [
                true,
                SearchPattern::BOOLEAN_CLAUSE_AND,
            ],
            [
                false,
                SearchPattern::BOOLEAN_CLAUSE_OR,
            ],
        ];
    }

    public function caseWithMatchingSearchPatternTermLength(): array
    {
        return [
            [
                true,
                'zeichn zeichent Büronetz 1000',
            ],
            [
                true,
                'zeichn zeichent 1000',
            ],
            [
                true,
                'zeichn 1 2',
            ],
            [
                true,
                '1 2',
            ],
            [
                true,
                'again 2',
            ],
            [
                false,
                'zeichn zeichent Büronetz 1000',
            ],
            [
                false,
                'zeichn zeichent 1000',
            ],
            [
                false,
                'zeichn 1 2',
            ],
            [
                false,
                '1 2',
            ],
            [
                false,
                'again 2',
            ],
        ];
    }

    public function testLevenshteinCharacterLimit(): void
    {
        if (\PHP_VERSION_ID >= 80000) {
            static::markTestSkipped();
        }

        // 256 characters
        $word = 'Kk5zWGZaYUnONSFzLplcuNyRUtDJl6DfrgYsFK30zo7iN9aTVdJx91OXa4mbZy7fQkCwvGbeCueNCNcveTg5'
            . 'Du9Bm2CaZdlOB4ZQG1OTzgZpFjyGaqMb4WRFU9NamzBPMZBN0b0RF32uDCvZXAiFnYJboSn6dwDgbTUE6Ibyyt'
            . 'OJZqtIF8bWn6GFzczaW5DzBqyjFriqCel3VqVMcLEOx6fWYfcQqn6sG8yAf4svUkeHc1iw8sIajbRjRCyeOF8w';

        $this->connection->insert('product_keyword_dictionary', [
            'id' => Uuid::randomBytes(),
            'keyword' => $word,
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
        ]);

        $pattern = $this->interpreter->interpret($word, Context::createDefaultContext());

        static::assertNotEmpty($pattern->getAllTerms());
    }

    private function setupKeywords(): void
    {
        $keywords = [
            'zeichnet',
            'zweichnet',
            'ausgezeichnet',
            'verkehrzeichennetzwerk',
            'gezeichnet',
            'zeichen',
            'zweideutige',
            'zweier',
            'zweite',
            'zweiteilig',
            'zweiten',
            'zweites',
            'zweiweg',
            'zweifellos',
            'büronetzwerk',
            'heimnetzwerk',
            'netzwerk',
            'netzwerkadapter',
            'netzwerkbuchse',
            'netzwerkcontroller',
            'netzwerkdrucker',
            'netzwerke',
            'netzwerken',
            'netzwerkinfrastruktur',
            'netzwerkkabel',
            'netzwerkkabels',
            'netzwerkkarte',
            'netzwerklösung',
            'netzwerkschnittstelle',
            'netzwerkschnittstellen',
            'netzwerkspeicher',
            'netzwerkspeicherlösung',
            'netzwerkspieler',
            'schwarzweiß',
            'netzwerkprotokolle',
            '10000',
            '10001',
            '10002',
            '10007',
            '10',
            '2',
            '3',
        ];

        $keywords = array_merge($keywords, [
            'between',
            'against',
            'betweencoffee',
            'betweenbike',
        ]);

        $languageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        foreach ($keywords as $keyword) {
            preg_match_all('/./us', $keyword, $ar);

            $this->connection->insert('product_keyword_dictionary', [
                'id' => Uuid::randomBytes(),
                'keyword' => $keyword,
                'language_id' => $languageId,
            ]);
        }
    }

    private function getProductSearchConfigId(): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('languageId', Defaults::LANGUAGE_SYSTEM)
        );

        return $this->productSearchConfigRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
    }
}
