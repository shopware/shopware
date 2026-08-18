<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\DataAbstractionLayer;

use OpenSearch\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\ElasticsearchException;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\AbstractElasticsearchAggregationHydrator;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntityAggregator;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\Event\ElasticsearchEntityAggregatorSearchedEvent;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\Event\ElasticsearchEntityAggregatorSearchEvent;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElasticsearchEntityAggregator::class)]
class ElasticsearchEntityAggregatorTest extends TestCase
{
    public function testNoAggregations(): void
    {
        $criteria = new Criteria();

        $client = $this->createMock(Client::class);
        // client should not be used if limit is 0
        $client->expects($this->never())
            ->method('search');

        $helper = static::createStub(ElasticsearchHelper::class);
        $helper
            ->method('allowSearch')
            ->willReturn(true);
        $helper
            ->method('addTerm')
            ->willThrowException(ElasticsearchException::emptyQuery());

        $searcher = new ElasticsearchEntityAggregator(
            $helper,
            $client,
            static::createStub(EntityAggregatorInterface::class),
            static::createStub(AbstractElasticsearchAggregationHydrator::class),
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

    public function testEmptyQueryExceptionIsCatched(): void
    {
        $criteria = new Criteria();

        $client = $this->createMock(Client::class);
        // client should not be used if limit is 0
        $client->expects($this->never())
            ->method('search');

        $helper = static::createStub(ElasticsearchHelper::class);
        $helper
            ->method('allowSearch')
            ->willReturn(true);
        $helper
            ->method('addTerm')
            ->willThrowException(ElasticsearchException::emptyQuery());

        $searcher = new ElasticsearchEntityAggregator(
            $helper,
            $client,
            static::createStub(EntityAggregatorInterface::class),
            static::createStub(AbstractElasticsearchAggregationHydrator::class),
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
        $criteria->addAggregation(new TermsAggregation('test', 'test'));

        $client = $this->createMock(Client::class);

        $client->expects($this->once())
            ->method('search')->with([
                'index' => '',
                'body' => [
                    'timeout' => '10s',
                    'size' => 0,
                    'track_total_hits' => false,
                ],
                'search_type' => 'dfs_query_then_fetch',
            ])->willReturn([]);

        $helper = static::createStub(ElasticsearchHelper::class);
        $helper
            ->method('allowSearch')
            ->willReturn(true);

        $searcher = new ElasticsearchEntityAggregator(
            $helper,
            $client,
            static::createStub(EntityAggregatorInterface::class),
            static::createStub(AbstractElasticsearchAggregationHydrator::class),
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

    public function testDispatchEvents(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addAggregation(new TermsAggregation('test', 'test'));

        $context = Context::createDefaultContext();

        $client = $this->createMock(Client::class);

        $client->expects($this->once())
            ->method('search')->with([
                'index' => '',
                'body' => [
                    'timeout' => '10s',
                    'size' => 0,
                    'track_total_hits' => false,
                ],
                'search_type' => 'dfs_query_then_fetch',
            ])->willReturn([
                'hits' => [
                    'hits' => [],
                ],
            ]);

        $helper = static::createStub(ElasticsearchHelper::class);
        $helper
            ->method('allowSearch')
            ->willReturn(true);

        $dispatcher = new EventDispatcher();
        $searchEventDispatched = false;
        $searchedEventDispatched = false;

        $dispatcher->addListener(ElasticsearchEntityAggregatorSearchEvent::class, static function (ElasticsearchEntityAggregatorSearchEvent $event) use (&$searchEventDispatched): void {
            $searchEventDispatched = true;
        });

        $dispatcher->addListener(ElasticsearchEntityAggregatorSearchedEvent::class, static function (ElasticsearchEntityAggregatorSearchedEvent $event) use (&$searchedEventDispatched): void {
            $searchedEventDispatched = true;
        });

        $aggregator = new ElasticsearchEntityAggregator(
            $helper,
            $client,
            static::createStub(EntityAggregatorInterface::class),
            static::createStub(AbstractElasticsearchAggregationHydrator::class),
            $dispatcher,
            '10s',
            'dfs_query_then_fetch'
        );

        $aggregator->aggregate(
            new ProductDefinition(),
            $criteria,
            $context
        );

        static::assertTrue($searchEventDispatched);
        static::assertTrue($searchedEventDispatched);
    }
}
