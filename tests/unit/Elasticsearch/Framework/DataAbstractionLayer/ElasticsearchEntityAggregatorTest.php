<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\DataAbstractionLayer;

use OpenSearch\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Elasticsearch\ElasticsearchException;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\AbstractElasticsearchAggregationHydrator;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntityAggregator;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(ElasticsearchEntityAggregator::class)]
class ElasticsearchEntityAggregatorTest extends TestCase
{
    public function testEmptyQueryExceptionIsCatched(): void
    {
        $criteria = new Criteria();

        $client = $this->createMock(Client::class);
        // client should not be used if limit is 0
        $client->expects(static::never())
            ->method('search');

        $helper = $this->createMock(ElasticsearchHelper::class);
        $helper
            ->method('allowSearch')
            ->willReturn(true);
        $helper
            ->method('addTerm')
            ->willThrowException(ElasticsearchException::emptyQuery());

        $searcher = new ElasticsearchEntityAggregator(
            $helper,
            $client,
            $this->createMock(EntityAggregatorInterface::class),
            $this->createMock(AbstractElasticsearchAggregationHydrator::class),
            new EventDispatcher(),
            '10s',
            'dfs_query_then_fetch'
        );

        $context = Context::createDefaultContext();

        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $result = $searcher->aggregate(
            new ProductDefinition(),
            $criteria,
            $context
        );

        static::assertCount(0, $result->getElements());
    }

    public function testAggregateWithTimeout(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);

        $client = $this->createMock(Client::class);

        $client->expects(static::once())
            ->method('search')->with([
                'index' => '',
                'body' => [
                    'timeout' => '10s',
                    'size' => 0,
                ],
                'search_type' => 'dfs_query_then_fetch',
            ])->willReturn([]);

        $helper = $this->createMock(ElasticsearchHelper::class);
        $helper
            ->method('allowSearch')
            ->willReturn(true);

        $searcher = new ElasticsearchEntityAggregator(
            $helper,
            $client,
            $this->createMock(EntityAggregatorInterface::class),
            $this->createMock(AbstractElasticsearchAggregationHydrator::class),
            new EventDispatcher(),
            '10s',
            'dfs_query_then_fetch'
        );

        $context = Context::createDefaultContext();

        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $searcher->aggregate(
            new ProductDefinition(),
            $criteria,
            $context
        );
    }
}
