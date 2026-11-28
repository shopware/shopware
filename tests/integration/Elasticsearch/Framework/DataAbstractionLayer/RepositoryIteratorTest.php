<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Framework\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[Package('framework')]
class RepositoryIteratorTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use ElasticsearchTestTestBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;

    public function testFetchIdsWalksAnOpenSearchServedSearch(): void
    {
        $this->clearElasticsearch();
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM product');

        $ids = new IdsCollection();
        static::getContainer()->get('product.repository')->create([
            (new ProductBuilder($ids, 'p1'))->price(100)->visibility()->build(),
            (new ProductBuilder($ids, 'p2'))->price(100)->visibility()->build(),
            (new ProductBuilder($ids, 'p3'))->price(100)->visibility()->build(),
            (new ProductBuilder($ids, 'p4'))->price(100)->visibility()->build(),
            (new ProductBuilder($ids, 'p5'))->price(100)->visibility()->build(),
            (new ProductBuilder($ids, 'not-in-the-search-index'))->price(100)->build(),
        ], Context::createDefaultContext());

        $this->indexElasticSearch();

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setLimit(2);

        $iterator = new RepositoryIterator(static::getContainer()->get('product.repository'), Context::createDefaultContext(), $criteria);

        $iterated = [];
        while (($page = $iterator->fetchIds()) !== null) {
            array_push($iterated, ...$page);
        }

        static::assertEqualsCanonicalizing(array_values($ids->getList(['p1', 'p2', 'p3', 'p4', 'p5'])), $iterated);
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }
}
