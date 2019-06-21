<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Test;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityAggregator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntitySearcher;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntityAggregator;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearcher;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\EntityIndexer;

trait ElasticsearchTestTestBehaviour
{
    use IntegrationTestBehaviour;

    /**
     * @before
     */
    public function enableElasticsearch(): void
    {
        KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(ElasticsearchHelper::class)
            ->setEnabled(true);

        KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(EntityIndexer::class)
            ->setEnabled(true);
    }

    /**
     * @after
     */
    public function disableElasticsearch(): void
    {
        KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(ElasticsearchHelper::class)
            ->setEnabled(false);

        KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(EntityIndexer::class)
            ->setEnabled(false);
    }

    public function indexElasticSearch(): void
    {
        KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(EntityIndexer::class)
            ->index(new \DateTime());
    }

    protected function createEntityAggregator(): ElasticsearchEntityAggregator
    {
        $decorated = $this->createMock(EntityAggregator::class);

        $decorated
            ->expects(static::never())
            ->method('aggregate');

        return new ElasticsearchEntityAggregator(
            $this->registry,
            $this->getContainer()->get(ElasticsearchHelper::class),
            $this->client,
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
            $this->client,
            $decorated,
            $this->registry,
            $this->getContainer()->get(ElasticsearchHelper::class)
        );
    }
}
