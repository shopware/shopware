<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch;

use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Elasticsearch;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

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

        $framework = new FrameworkBundle();
        $frameworkExtension = $framework->getContainerExtension();
        static::assertNotNull($frameworkExtension);
        $container->registerExtension($frameworkExtension);

        $bundle = new Elasticsearch();
        $extension = $bundle->getContainerExtension();
        static::assertInstanceOf(ExtensionInterface::class, $extension);
        $container->registerExtension($extension);
        $bundle->build($container);

        static::assertTrue($container->hasDefinition(ElasticsearchIndexer::class));
    }
}
