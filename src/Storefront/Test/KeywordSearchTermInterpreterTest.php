<?php declare(strict_types=1);

namespace Shopware\Storefront\Test;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Entity\Search\Term\SearchTerm;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Defaults;
use Shopware\Storefront\Page\Search\KeywordSearchTermInterpreter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class KeywordSearchTermInterpreterTest extends KernelTestCase
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    private $connection;

    /**
     * @var KeywordSearchTermInterpreter
     */
    private $interpreter;

    public function setUp()
    {
        self::bootKernel();
        $container = self::$kernel->getContainer();

        $this->connection = $container->get(Connection::class);
        $this->connection->beginTransaction();
        $this->interpreter = $container->get(KeywordSearchTermInterpreter::class);
        $this->connection->executeUpdate('DELETE FROM search_keyword');

        $this->setupKeywords();
    }

    public function tearDown()
    {
        $this->connection->rollBack();

        parent::tearDown();
    }

    /**
     * @dataProvider cases
     *
     * @param string $term
     * @param array  $expected
     */
    public function testMatching(string $term, array $expected)
    {
        $context = TranslationContext::createDefaultContext();

        $matches = $this->interpreter->interpret($term, $context);

        $keywords = array_map(function (SearchTerm $term) {
            return $term->getTerm();
        }, $matches->getTerms());

        self::assertEquals($expected, $keywords);
    }

    public function cases()
    {
        return [
            [
                'zeichn',
                ['zeichnet', 'zeichen', 'zweichnet', 'gezeichnet', 'ausgezeichnet', 'verkehrzeichennetzwerk'],
            ],
            [
                'zeichent',
                ['zeichen', 'zeichnet', 'gezeichnet', 'ausgezeichnet', 'verkehrzeichennetzwerk'],
            ],
            [
                'Büronetz',
                ['büronetzwerk'],
            ],
        ];
    }

    private function setupKeywords()
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

        foreach ($keywords as $keyword) {
            $this->connection->insert('search_keyword', [
                'keyword' => $keyword,
                'shop_id' => Uuid::fromString(Defaults::SHOP)->getBytes(),
                'version_id' => Uuid::fromString(Defaults::LIVE_VERSION)->getBytes(),
                'shop_version_id' => Uuid::fromString(Defaults::LIVE_VERSION)->getBytes(),
            ]);
        }
    }
}
