<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Test\Framework\DataAbstractionLayer;

use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\AbstractElasticsearchSearchHydrator;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearcher;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
class ElasticsearchEntitySearcherTest extends TestCase
{
    use ElasticsearchTestTestBehaviour;
    use IntegrationTestBehaviour;

    public function testWithCriteriaLimitOfZero(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(0);

        $client = $this->createMock(Client::class);
        // client should not be used if limit is 0
        $client->expects(static::never())
            ->method('search');

        $searcher = new ElasticsearchEntitySearcher(
            $client,
            $this->getContainer()->get(EntitySearcherInterface::class),
            $this->getContainer()->get(ElasticsearchHelper::class),
            $this->getContainer()->get(CriteriaParser::class),
            $this->getContainer()->get(AbstractElasticsearchSearchHydrator::class),
            $this->getContainer()->get('event_dispatcher')
        );

        $context = Context::createDefaultContext();
        $context->addState(Context::STATE_ELASTICSEARCH_AWARE);

        $result = $searcher->search(
            $this->getContainer()->get(ProductDefinition::class),
            $criteria,
            $context
        );

        static::assertEquals(0, $result->getTotal());
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
            $this->getContainer()->get('Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearcher.inner'),
            $helper,
            $this->getContainer()->get(CriteriaParser::class),
            $this->getContainer()->get(AbstractElasticsearchSearchHydrator::class),
            $this->getContainer()->get('event_dispatcher')
        );

        $context = Context::createDefaultContext();
        $context->addState(Context::STATE_ELASTICSEARCH_AWARE);

        try {
            $result = $searcher->search(
                $this->getContainer()->get(ProductDefinition::class),
                $criteria,
                $context
            );

            static::assertEquals(0, $result->getTotal());
        } catch (NoNodesAvailableException $e) {
        }
    }

    protected function getDiContainer(): ContainerInterface
    {
        return $this->getContainer();
    }

    protected function runWorker(): void
    {
    }
}
