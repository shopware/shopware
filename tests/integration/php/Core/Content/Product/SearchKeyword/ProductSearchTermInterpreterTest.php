<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\SearchKeyword;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreter;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreterInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTerm;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\ArrayNormalizer;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreter
 */
class ProductSearchTermInterpreterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private ProductSearchTermInterpreterInterface $interpreter;

    private EntityRepository $productSearchConfigRepository;

    private string $productSearchConfigId;

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
     *
     * @param list<string> $expected
     */
    public function testMatching(string $term, array $expected): void
    {
        $context = Context::createDefaultContext();

        $matches = $this->interpreter->interpret($term, $context);

        $keywords = array_map(fn (SearchTerm $term) => $term->getTerm(), $matches->getTerms());

        sort($expected);
        sort($keywords);
        static::assertEquals($expected, $keywords);
    }

    /**
     * @dataProvider casesWithTokenFilter
     *
     * @param list<string> $expected
     */
    public function testMatchingWithTokenFilter(string $term, array $expected): void
    {
        $context = Context::createDefaultContext();

        $matches = $this->interpreter->interpret($term, $context);

        $keywords = array_map(fn (SearchTerm $term) => $term->getTerm(), $matches->getTerms());

        sort($expected);
        sort($keywords);
        static::assertEquals($expected, $keywords);
    }

    /**
     * @dataProvider caseWithFetchingTokenTerms
     *
     * @param list<list<string>> $expected
     */
    public function testMatchingTokenTerms(string $term, array $expected): void
    {
        $context = Context::createDefaultContext();

        $tokenTerms = $this->interpreter->interpret($term, $context)->getTokenTerms();

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
        $terms = array_map(fn (SearchTerm $term) => $term->getTerm(), $matches->getTerms());

        if (!$andLogic) {
            $flatterTerms = ArrayNormalizer::flatten($matches->getTokenTerms());

            static::assertLessThanOrEqual(\count($flatterTerms), \count($terms));
            static::assertLessThanOrEqual(8, \count($terms));

            return;
        }

        static::assertGreaterThanOrEqual(0, \count($terms));
    }

    /**
     * @return array<array{0: string, 1: list<string>}>
     */
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
                ['100', '10000', '10001', '10002', '10007'],
            ],
            'test it uses only first 8 keywords' => [
                '10',
                ['10', '100', '101', '102', '103', '10000', '10001', '10002'],
            ],
        ];
    }

    /**
     * @return array<array{0: string, 1: list<string>}>
     */
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
                ['100', '10000', '10001', '10002', '10007'],
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

    /**
     * @return array<array{0: string, 1: list<list<string>>}>
     */
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
                    ['100', '10000', '10001', '10002', '10007'],
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
                    ['100', '10000', '10001', '10002', '10007'],
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
                    ['100', '10000', '10001', '10002', '10007'],
                ],
            ],
            [
                '‰€€Büronet¥Æ ‡‡1000††',
                [
                    ['büronetzwerk'],
                    ['100', '10000', '10001', '10002', '10007'],
                ],
            ],
        ];
    }

    /**
     * @return array<array{0: bool, 1: string}>
     */
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

    /**
     * @return array<array{0: bool, 1: string}>
     */
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
            '100',
            '101',
            '102',
            '103',
            '2',
            '3',
            'between',
            'against',
            'betweencoffee',
            'betweenbike',
        ];

        $languageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        foreach ($keywords as $keyword) {
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

        return (string) $this->productSearchConfigRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
    }
}
