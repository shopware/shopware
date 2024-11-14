<?php declare(strict_types=1);

namespace Shopware\Elasticsearch;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\DependencyInjection\ElasticsearchExtension;
use Shopware\Elasticsearch\DependencyInjection\ElasticsearchMigrationCompilerPass;
use Shopware\Elasticsearch\Profiler\ElasticsearchProfileCompilerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

/**
 * @internal
 */
#[Package('core')]
class Elasticsearch extends Bundle
{
    public function getTemplatePriority(): int
    {
        return -1;
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $this->buildDefaultConfig($container);

        $container->addCompilerPass(new ElasticsearchMigrationCompilerPass());

        // Needs to run before the ProfilerPass
        $container->addCompilerPass(new ElasticsearchProfileCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 5000);
    }

    protected function createContainerExtension(): ?ExtensionInterface
    {
        return new ElasticsearchExtension();
    }
}
