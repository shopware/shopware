<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch;

use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Elasticsearch;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Elasticsearch
 */
class ElasticsearchTest extends TestCase
{
    public function testTemplatePriority(): void
    {
        $elasticsearch = new Elasticsearch();

        static::assertEquals(-1, $elasticsearch->getTemplatePriority());
    }

    public function testBundle(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        $bundle = new Elasticsearch();
        $container->registerExtension($bundle->createContainerExtension());
        $bundle->build($container);

        static::assertTrue($container->hasDefinition(ElasticsearchIndexer::class));
    }

    public function testBundleWithInvalidEnvironment(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 1);

        $bundle = new Elasticsearch();
        $container->registerExtension($bundle->createContainerExtension());

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Container parameter "kernel.environment" needs to be a string');
        $bundle->build($container);
    }
}
