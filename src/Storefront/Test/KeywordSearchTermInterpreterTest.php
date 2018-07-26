<?php declare(strict_types=1);

namespace Shopware\Storefront\Test;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Search\Term\SearchTerm;
use Shopware\Core\Framework\Search\Util\KeywordSearchTermInterpreterInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class KeywordSearchTermInterpreterTest extends KernelTestCase
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    private $connection;

    /**
     * @var KeywordSearchTermInterpreterInterface
     */
    private $interpreter;

    public function setUp()
    {
        self::bootKernel();

        $this->connection = self::$container->get(Connection::class);
        $this->interpreter = self::$container->get(KeywordSearchTermInterpreterInterface::class);
        $this->connection->beginTransaction();
        $this->connection->executeUpdate('DELETE FROM search_dictionary');

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
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $matches = $this->interpreter->interpret($term, 'product', $context);

        $keywords = array_map(function (SearchTerm $term) {
            return $term->getTerm();
        }, $matches->getTerms());

        sort($expected);
        sort($keywords);
        self::assertEquals($expected, $keywords);
    }

    public function cases()
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

        $languageId = Uuid::fromString(Defaults::LANGUAGE)->getBytes();
        $versionId = Uuid::fromString(Defaults::LIVE_VERSION)->getBytes();
        $tenantId = Uuid::fromString(Defaults::TENANT_ID)->getBytes();

        foreach ($keywords as $keyword) {
            preg_match_all('/./us', $keyword, $ar);

            $this->connection->insert('search_dictionary', [
                'tenant_id' => $tenantId,
                'scope' => 'product',
                'keyword' => $keyword,
                'reversed' => implode('', array_reverse($ar[0])),
                'version_id' => $versionId,
                'language_id' => $languageId,
                'language_tenant_id' => $tenantId,
            ]);
        }
    }
}
