<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearcher;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[CoversClass(ElasticsearchEntitySearcher::class)]
class SearchCasesTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use ElasticsearchTestTestBehaviour;
    use KernelTestBehaviour;

    private static IdsCollection $ids;

    /**
     * @param array<mixed> $products
     */
    #[DataProvider('numbersProvider')]
    public function testSearch(array $products, string $term, string $best): void
    {
        $this->clearElasticsearch();

        $this->getContainer()->get(Connection::class)->executeStatement('DELETE FROM product');

        $this->getContainer()->get('product.repository')->create(array_values($products), Context::createDefaultContext());

        $this->indexElasticSearch();

        $searcher = $this->createEntitySearcher();

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm($term);

        $definition = $this->getContainer()->get(ProductDefinition::class);

        $result = $searcher->search($definition, $criteria, Context::createDefaultContext());

        $scores = [];
        foreach ($result->getData() as $item) {
            $scores[self::$ids->getKey((string) $item['id'])] = $item['_score'];
        }

        static::assertEquals(
            $best,
            self::$ids->getKey((string) $result->firstId()),
            print_r($scores, true)
        );
    }

    public static function numbersProvider(): \Generator
    {
        self::$ids = $ids = new IdsCollection();

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

        yield 'Exact number match' => [$products, 'DE-031668-B', 'p1'];
    }

    protected function getDiContainer(): ContainerInterface
    {
        return $this->getContainer();
    }

    protected function runWorker(): void
    {
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
