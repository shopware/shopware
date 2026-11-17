<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Framework\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\AbstractElasticsearchSearchHydrator;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearcher;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\Event\ElasticsearchEntitySearcherSearchEvent;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
class ElasticsearchEntitySearcherTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use ElasticsearchTestTestBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;

    public function testSearchWithoutLimitReturnsMatchesBeyondTheFirstPage(): void
    {
        $ids = $this->indexProducts();

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsFilter('active', true));

        $result = $this->createSearcherWithPageSize(2)->search(
            static::getContainer()->get(ProductDefinition::class),
            $criteria,
            Context::createDefaultContext()
        );

        static::assertEqualsCanonicalizing(
            array_values($ids->getList(['stock-5', 'stock-4', 'stock-3', 'stock-2', 'stock-1'])),
            $result->getIds()
        );
    }

    public function testSearchWithoutLimitKeepsTheSortingAcrossPages(): void
    {
        $ids = $this->indexProducts();

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addSorting(new FieldSorting('stock', FieldSorting::DESCENDING));

        $result = $this->createSearcherWithPageSize(2)->search(
            static::getContainer()->get(ProductDefinition::class),
            $criteria,
            Context::createDefaultContext()
        );

        static::assertSame(
            array_values($ids->getList(['stock-5', 'stock-4', 'stock-3', 'stock-2', 'stock-1'])),
            $result->getIds()
        );
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }

    private function indexProducts(): IdsCollection
    {
        $this->clearElasticsearch();
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM product');

        $ids = new IdsCollection();

        static::getContainer()->get('product.repository')->create([
            (new ProductBuilder($ids, 'stock-5'))->price(100)->stock(5)->visibility()->build(),
            (new ProductBuilder($ids, 'stock-4'))->price(100)->stock(4)->visibility()->build(),
            (new ProductBuilder($ids, 'stock-3'))->price(100)->stock(3)->visibility()->build(),
            (new ProductBuilder($ids, 'stock-2'))->price(100)->stock(2)->visibility()->build(),
            (new ProductBuilder($ids, 'stock-1'))->price(100)->stock(1)->visibility()->build(),
            (new ProductBuilder($ids, 'inactive'))->price(100)->stock(10)->visibility()->active(false)->build(),
        ], Context::createDefaultContext());

        $this->indexElasticSearch();

        return $ids;
    }

    private function createSearcherWithPageSize(int $pageSize): ElasticsearchEntitySearcher
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ElasticsearchEntitySearcherSearchEvent::class, static function (ElasticsearchEntitySearcherSearchEvent $event) use ($pageSize): void {
            $event->getSearch()->setSize($pageSize);
        });

        $decorated = $this->createMock(EntitySearcherInterface::class);
        $decorated->expects($this->never())->method('search');

        return new ElasticsearchEntitySearcher(
            static::getContainer()->get(Client::class),
            $decorated,
            static::getContainer()->get(ElasticsearchHelper::class),
            static::getContainer()->get(CriteriaParser::class),
            static::getContainer()->get(AbstractElasticsearchSearchHydrator::class),
            $dispatcher,
            '5s',
            'query_then_fetch'
        );
    }
}
