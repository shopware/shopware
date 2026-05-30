<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\FilesystemBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SessionTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
class SearchCasesTest extends TestCase
{
    use CacheTestBehaviour;
    use ElasticsearchTestTestBehaviour;
    use FilesystemBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use SessionTestBehaviour;

    /**
     * @param array<mixed> $products
     */
    #[DataProvider('numbersProvider')]
    public function testSearch(IdsCollection $ids, array $products, string $term, string $best): void
    {
        $this->clearElasticsearch();

        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM product');

        static::getContainer()->get('product.repository')->create(array_values($products), Context::createDefaultContext());

        $this->setSearchConfiguration(true, ['name', 'productNumber']);
        $this->setSearchScores(['name' => 700, 'productNumber' => 1000]);

        $this->indexElasticSearch();

        $searcher = $this->createEntitySearcher();

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm($term);

        $definition = static::getContainer()->get(ProductDefinition::class);

        $result = $searcher->search($definition, $criteria, Context::createDefaultContext());

        $scores = [];
        foreach ($result->getData() as $item) {
            $key = $ids->getKey((string) $item['id']);
            static::assertNotNull($key);
            $scores[$key] = $item['_score'];
        }

        $firstId = $result->firstId();
        static::assertNotNull($firstId, print_r($scores, true));
        static::assertSame($best, $ids->getKey($firstId), print_r($scores, true));
    }

    public static function numbersProvider(): \Generator
    {
        $ids = new IdsCollection();

        $products = [
            'p1' => self::product($ids, 'p1', 'DE-031668-B', 'HP LaserJet Enterprise M608x Inkl. Stapelfach und Papierfach'),
            'p2' => self::product($ids, 'p2', 'DE-031677-B', 'HP LaserJet Enterprise M608x Inkl. Stapelfach'),
            'p3' => self::product($ids, 'p3', 'DE-031687-B', 'HP LaserJet Enterprise M608x'),
            'p4' => self::product($ids, 'p4', 'DE-13.116-B', 'LG 24MB35PM-B - 1920 x 1080 - FHD'),
            'p5' => self::product($ids, 'p5', 'DE-15.174-N', 'Crucial DDR4 Desktop Speicher - DIMM - DDR4 - 2400 MHz - CL17'),
            'p6' => self::product($ids, 'p6', 'DE-17.028-A', 'Fujitsu Display B24-8 TE - 1920 x 1080 - FHD'),
            'p7' => self::product($ids, 'p7', 'DE-17.028-B', 'Fujitsu Display B24-8 TE - 1920 x 1080 - FHD'),
            'p8' => self::product($ids, 'p8', 'DE-17.346-B', 'LG 24BK550Y-B - 1920 x 1080 - FHD'),
            'p9' => self::product($ids, 'p9', 'DE-17.353-B', 'Eizo FlexScan EV2416W-BK - 1920 x 1200 - WUXGA'),
            'p10' => self::product($ids, 'p10', 'DE-17.447-N', 'SOLID DDR3 Desktop Speicher - DIMM 240-PIN - DDR3 - 1600 MHz - CL 11'),
        ];

        yield 'Exact number match' => [$ids, $products, 'DE-031668-B', 'p1'];
    }

    public function testExactNameTokenMatchRanksAheadOfPrefixOnlyMatch(): void
    {
        $this->clearElasticsearch();

        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM product');

        $ids = new IdsCollection();

        static::getContainer()->get('product.repository')->create([
            self::product($ids, 'exact', 'DE-EXACT-1', 'Leather Jacket'),
            self::product($ids, 'prefix', 'DE-PREFIX-1', 'Leathery Jacket'),
        ], Context::createDefaultContext());

        $this->setSearchConfiguration(true, ['name']);
        $this->setSearchScores(['name' => 700]);

        $this->indexElasticSearch();

        $searcher = $this->createEntitySearcher();

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm('Leather');

        $definition = static::getContainer()->get(ProductDefinition::class);

        $result = $searcher->search($definition, $criteria, Context::createDefaultContext());

        $firstId = $result->firstId();
        static::assertNotNull($firstId, print_r($result->getData(), true));
        static::assertSame('exact', $ids->getKey($firstId), print_r($result->getData(), true));
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }

    /**
     * @return array<string, mixed>
     */
    private static function product(IdsCollection $ids, string $key, string $number, string $name): array
    {
        return (new ProductBuilder($ids, $key))
            ->number($number)
            ->price(100)
            ->visibility()
            ->name($name)
            ->build();
    }
}
