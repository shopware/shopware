<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Profiler;

use OpenSearch\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Profiler\ClientProfiler;
use Shopware\Elasticsearch\Profiler\DataCollector;
use Shopware\Elasticsearch\Profiler\ElasticsearchProfileCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[CoversClass(ElasticsearchProfileCompilerPass::class)]
class ElasticsearchProfileCompilerPassTest extends TestCase
{
    public function testCompilerPassRemovesDataCollector(): void
    {
        $container = new ContainerBuilder();

        $def = new Definition(DataCollector::class);
        $def->setPublic(true);

        $container->setDefinition(DataCollector::class, $def);

        $container->setParameter('kernel.debug', false);

        $compilerPass = new ElasticsearchProfileCompilerPass();
        $compilerPass->process($container);

        static::assertFalse($container->hasDefinition(DataCollector::class));
    }

    public function testCompilerPassDecoratesClient(): void
    {
        $container = new ContainerBuilder();

        $def = new Definition(DataCollector::class);
        $def->setPublic(true);
        $container->setDefinition(DataCollector::class, $def);

        $def = new Definition(Client::class);
        $def->setPublic(true);
        $container->setDefinition(Client::class, $def);

        $def = new Definition(Client::class);
        $def->setPublic(true);
        $container->setDefinition('admin.openSearch.client', $def);

        $container->setParameter('kernel.debug', true);

        $compilerPass = new ElasticsearchProfileCompilerPass();
        $compilerPass->process($container);

        $container->compile();

        static::assertTrue($container->hasDefinition(Client::class));
        static::assertTrue($container->hasDefinition('admin.openSearch.client'));
        static::assertSame(ClientProfiler::class, $container->getDefinition(Client::class)->getClass());
        static::assertSame(ClientProfiler::class, $container->getDefinition('admin.openSearch.client')->getClass());
    }
}
