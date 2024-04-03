<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\DataAbstractionLayer;

use OpenSearch\Client;
use OpenSearch\Common\Exceptions\NoNodesAvailableException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Elasticsearch\ElasticsearchException;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\AbstractElasticsearchSearchHydrator;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearcher;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(ElasticsearchEntitySearcher::class)]
class ElasticsearchEntitySearcherTest extends TestCase
{
    public function testEmptyQueryExceptionIsCatched(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);

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

        $searcher = new ElasticsearchEntitySearcher(
            $client,
            $this->createMock(EntitySearcherInterface::class),
            $helper,
            $this->createMock(CriteriaParser::class),
            $this->createMock(AbstractElasticsearchSearchHydrator::class),
            new EventDispatcher(),
        );

        $context = Context::createDefaultContext();

        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $result = $searcher->search(
            new ProductDefinition(),
            $criteria,
            $context
        );

        static::assertEquals(0, $result->getTotal());
    }

    public function testWithCriteriaLimitOfZero(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(0);

        $client = $this->createMock(Client::class);
        // client should not be used if limit is 0
        $client->expects(static::never())
            ->method('search');

        $helper = $this->createMock(ElasticsearchHelper::class);
        $helper
            ->method('allowSearch')
            ->willReturn(true);

        $searcher = new ElasticsearchEntitySearcher(
            $client,
            $this->createMock(EntitySearcherInterface::class),
            $helper,
            $this->createMock(CriteriaParser::class),
            $this->createMock(AbstractElasticsearchSearchHydrator::class),
            new EventDispatcher(),
        );

        $context = Context::createDefaultContext();

        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $result = $searcher->search(
            new ProductDefinition(),
            $criteria,
            $context
        );

        static::assertEquals(0, $result->getTotal());
    }

    public function testSearchWithTimeout(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);

        $client = $this->createMock(Client::class);

        $client->expects(static::once())
            ->method('search')->with([
                'index' => '',
                'track_total_hits' => true,
                'body' => [
                    'timeout' => '10s',
                    'from' => 0,
                    'size' => 10,
                ],
            ])->willReturn([]);

        $helper = $this->createMock(ElasticsearchHelper::class);
        $helper
            ->method('allowSearch')
            ->willReturn(true);

        $searcher = new ElasticsearchEntitySearcher(
            $client,
            $this->createMock(EntitySearcherInterface::class),
            $helper,
            $this->createMock(CriteriaParser::class),
            $this->createMock(AbstractElasticsearchSearchHydrator::class),
            new EventDispatcher(),
            '10s'
        );

        $context = Context::createDefaultContext();

        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $searcher->search(
            new ProductDefinition(),
            $criteria,
            $context
        );
    }

    public function testExceptionsGetLogged(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);

        $client = $this->createMock(Client::class);
        // client should not be used if limit is 0
        $client->expects(static::once())
            ->method('search')
            ->willThrowException(new NoNodesAvailableException());

        $helper = $this->createMock(ElasticsearchHelper::class);
        $helper->expects(static::once())->method('logAndThrowException');
        $helper->method('allowSearch')->willReturn(true);

        $searcher = new ElasticsearchEntitySearcher(
            $client,
            $this->createMock(EntitySearcherInterface::class),
            $helper,
            new CriteriaParser(new EntityDefinitionQueryHelper(), $this->createMock(CustomFieldService::class)),
            $this->createMock(AbstractElasticsearchSearchHydrator::class),
            new EventDispatcher(),
        );

        $context = Context::createDefaultContext();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $result = $searcher->search(
            new ProductDefinition(),
            $criteria,
            $context
        );

        static::assertEquals(0, $result->getTotal());
    }
}
