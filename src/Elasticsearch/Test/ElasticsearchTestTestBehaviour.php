<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Test;

use Elasticsearch\Client;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityAggregator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntitySearcher;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntityAggregator;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearcher;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\Indexing\CreateAliasTaskHandler;
use Shopware\Elasticsearch\Framework\Indexing\EntityIndexer;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait ElasticsearchTestTestBehaviour
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    /**
     * @before
     */
    public function enableElasticsearch(): void
    {
        $this->getContainer()
            ->get(ElasticsearchHelper::class)
            ->setEnabled(true);

        $this->getContainer()
            ->get(EntityIndexer::class)
            ->setEnabled(true);
    }

    /**
     * @after
     */
    public function disableElasticsearch(): void
    {
        $this->getContainer()
            ->get(ElasticsearchHelper::class)
            ->setEnabled(false);

        $this->getContainer()
            ->get(EntityIndexer::class)
            ->setEnabled(false);
    }

    public function indexElasticSearch(): void
    {
        $this->getContainer()
            ->get(EntityIndexer::class)
            ->index(new \DateTime());

        $this->runWorker();

        $this->getContainer()
            ->get(CreateAliasTaskHandler::class)
            ->run();
    }

    protected function createEntityAggregator(): ElasticsearchEntityAggregator
    {
        $decorated = $this->createMock(EntityAggregator::class);

        $decorated
            ->expects(static::never())
            ->method('aggregate');

        return new ElasticsearchEntityAggregator(
            $this->getContainer()->get(ElasticsearchHelper::class),
            $this->getContainer()->get(Client::class),
            $decorated,
            $this->getContainer()->get(DefinitionInstanceRegistry::class)
        );
    }

    protected function createEntitySearcher(): ElasticsearchEntitySearcher
    {
        $decorated = $this->createMock(EntitySearcher::class);

        $decorated
            ->expects(static::never())
            ->method('search');

        return new ElasticsearchEntitySearcher(
            $this->getContainer()->get(Client::class),
            $decorated,
            $this->getContainer()->get(ElasticsearchHelper::class),
            $this->getContainer()->get('logger')
        );
    }

    abstract protected function getContainer(): ContainerInterface;
}
