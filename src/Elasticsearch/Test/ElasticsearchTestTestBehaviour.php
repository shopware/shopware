<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Test;

use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
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
    }

    public function indexElasticSearch(): void
    {
        KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(EntityIndexer::class)
            ->setEnabled(true)
            ->index(new \DateTime());
    }
}
