<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Indexing;

use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;
use Shopware\Elasticsearch\Framework\Indexing\IndexManager;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(IndexManager::class)]
class IndexManagerTest extends TestCase
{
    public function testRefreshesEachRegisteredIndex(): void
    {
        $entityDefinition = $this->createMock(EntityDefinition::class);

        $esDefinition = $this->createMock(AbstractElasticsearchDefinition::class);
        $esDefinition->method('getEntityDefinition')->willReturn($entityDefinition);

        $registry = $this->createMock(ElasticsearchRegistry::class);
        $registry->method('getDefinitions')->willReturn([$esDefinition]);

        $helper = $this->createMock(ElasticsearchHelper::class);
        $helper->method('getIndexName')->with($entityDefinition)->willReturn('product-index');

        $indices = $this->createMock(IndicesNamespace::class);
        $indices->expects($this->once())
            ->method('refresh')
            ->with(['index' => 'product-index']);

        $client = $this->createMock(Client::class);
        $client->method('indices')->willReturn($indices);

        (new IndexManager($client, $helper, $registry))->refreshIndices();
    }

    public function testSwallowsRefreshExceptions(): void
    {
        $entityDefinition = $this->createMock(EntityDefinition::class);

        $esDefinition = $this->createMock(AbstractElasticsearchDefinition::class);
        $esDefinition->method('getEntityDefinition')->willReturn($entityDefinition);

        $registry = $this->createMock(ElasticsearchRegistry::class);
        $registry->method('getDefinitions')->willReturn([$esDefinition]);

        $helper = $this->createMock(ElasticsearchHelper::class);
        $helper->method('getIndexName')->willReturn('missing-index');

        $indices = $this->createMock(IndicesNamespace::class);
        $indices->method('refresh')->willThrowException(new \RuntimeException('index missing'));

        $client = $this->createMock(Client::class);
        $client->method('indices')->willReturn($indices);

        // assertion is "no exception escapes"
        (new IndexManager($client, $helper, $registry))->refreshIndices();

        $this->expectNotToPerformAssertions();
    }
}
