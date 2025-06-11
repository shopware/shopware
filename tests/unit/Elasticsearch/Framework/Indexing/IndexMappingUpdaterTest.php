<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Indexing;

use OpenSearch\Client;
use OpenSearch\Common\Exceptions\BadRequest400Exception;
use OpenSearch\Common\Exceptions\Missing404Exception;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Context;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;
use Shopware\Elasticsearch\Framework\Indexing\IndexMappingProvider;
use Shopware\Elasticsearch\Framework\Indexing\IndexMappingUpdater;
use Shopware\Elasticsearch\Framework\SystemUpdateListener;
use Shopware\Elasticsearch\Product\ElasticsearchProductDefinition;
use Shopware\Elasticsearch\Product\ElasticsearchProductException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(IndexMappingUpdater::class)]
class IndexMappingUpdaterTest extends TestCase
{
    public function testUpdateWithoutIndexingEnabled(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper->expects($this->once())->method('allowIndexing')->willReturn(false);

        $registry = new ElasticsearchRegistry([
            $this->createMock(ElasticsearchProductDefinition::class),
        ]);

        $client = $this->createMock(Client::class);
        $indicesNamespace = $this->createMock(IndicesNamespace::class);
        $indicesNamespace
            ->expects($this->never())
            ->method('putMapping')
            ->with([
                'index' => 'index',
                'body' => [
                    'foo' => '1',
                ],
            ]);

        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage->expects($this->never())->method('set');

        $indexMappingProvider = $this->createMock(IndexMappingProvider::class);
        $indexMappingProvider
            ->expects($this->never())
            ->method('build')
            ->willReturn(['foo' => '1']);

        $updater = new IndexMappingUpdater(
            $registry,
            $elasticsearchHelper,
            $client,
            $indexMappingProvider,
            $storage
        );

        $updater->update(Context::createDefaultContext());
    }

    public function testUpdate(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper->expects($this->once())->method('allowIndexing')->willReturn(true);

        $elasticsearchHelper->method('getIndexName')->willReturn('index');

        $registry = new ElasticsearchRegistry([
            $this->createMock(ElasticsearchProductDefinition::class),
        ]);

        $client = $this->createMock(Client::class);
        $indicesNamespace = $this->createMock(IndicesNamespace::class);
        $indicesNamespace
            ->expects($this->once())
            ->method('putMapping')
            ->with([
                'index' => 'index',
                'body' => [
                    'foo' => '1',
                ],
            ]);

        $client
            ->method('indices')
            ->willReturn($indicesNamespace);

        $indexMappingProvider = $this->createMock(IndexMappingProvider::class);
        $indexMappingProvider
            ->method('build')
            ->willReturn(['foo' => '1']);

        $updater = new IndexMappingUpdater(
            $registry,
            $elasticsearchHelper,
            $client,
            $indexMappingProvider,
            $this->createMock(AbstractKeyValueStorage::class),
        );

        $updater->update(Context::createDefaultContext());
    }

    public function testUpdateWithError(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper->method('getIndexName')->willReturn('index');
        $elasticsearchHelper->expects($this->once())->method('allowIndexing')->willReturn(true);

        $definition = $this->createMock(ElasticsearchProductDefinition::class);
        $definition
            ->method('getEntityDefinition')
            ->willReturn(new ProductDefinition());

        $registry = new ElasticsearchRegistry([$definition]);

        $client = $this->createMock(Client::class);
        $indicesNamespace = $this->createMock(IndicesNamespace::class);
        $indicesNamespace
            ->expects($this->once())
            ->method('putMapping')
            ->with([
                'index' => 'index',
                'body' => [
                    'foo' => '1',
                ],
            ])->willThrowException(new BadRequest400Exception('can\'t merge a non object mapping [completion] with an object mapping', Response::HTTP_BAD_REQUEST));

        $client
            ->method('indices')
            ->willReturn($indicesNamespace);

        $indexMappingProvider = $this->createMock(IndexMappingProvider::class);
        $indexMappingProvider
            ->method('build')
            ->willReturn(['foo' => '1']);

        $elasticsearchHelper->expects($this->once())->method('logAndThrowException')->with(
            static::callback(static function (ElasticsearchProductException $exception) {
                return $exception->getMessage() === 'One or more fields already exist in the index with different types. Please reset the index and rebuild it.';
            }),
        );

        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage->expects($this->once())
            ->method('set')
            ->with(
                SystemUpdateListener::CONFIG_KEY,
                ['product'],
            );

        $updater = new IndexMappingUpdater(
            $registry,
            $elasticsearchHelper,
            $client,
            $indexMappingProvider,
            $storage,
        );

        $updater->update(Context::createDefaultContext());
    }

    public function testUpdateWithMissingIndexError(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper->method('getIndexName')->willReturn('index');
        $elasticsearchHelper->expects($this->once())->method('allowIndexing')->willReturn(true);

        $definition = $this->createMock(ElasticsearchProductDefinition::class);
        $definition
            ->method('getEntityDefinition')
            ->willReturn(new ProductDefinition());

        $registry = new ElasticsearchRegistry([$definition]);

        $client = $this->createMock(Client::class);
        $indicesNamespace = $this->createMock(IndicesNamespace::class);
        $indicesNamespace
            ->expects($this->once())
            ->method('putMapping')
            ->with([
                'index' => 'index',
                'body' => [
                    'foo' => '1',
                ],
            ])->willThrowException(new Missing404Exception('no such index [index]', Response::HTTP_NOT_FOUND));

        $client
            ->method('indices')
            ->willReturn($indicesNamespace);

        $indexMappingProvider = $this->createMock(IndexMappingProvider::class);
        $indexMappingProvider
            ->method('build')
            ->willReturn(['foo' => '1']);

        $elasticsearchHelper->expects($this->once())->method('logAndThrowException')->with(
            static::callback(static function (Missing404Exception $exception) {
                return $exception->getMessage() === 'no such index [index]';
            }),
        );

        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage->expects($this->never())->method('set');

        $updater = new IndexMappingUpdater(
            $registry,
            $elasticsearchHelper,
            $client,
            $indexMappingProvider,
            $storage,
        );

        $updater->update(Context::createDefaultContext());
    }
}
