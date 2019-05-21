<?php declare(strict_types=1);

namespace Shopware\Storefront\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreter;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreterInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTerm;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
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

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->interpreter = $this->getContainer()->get(ProductSearchTermInterpreter::class);
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
        ];

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
}
