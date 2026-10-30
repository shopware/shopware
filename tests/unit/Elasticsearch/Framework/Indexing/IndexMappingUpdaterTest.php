<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Indexing;

use OpenSearch\Client;
use OpenSearch\Exception\BadRequestHttpException;
use OpenSearch\Exception\NotFoundHttpException;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;
use Shopware\Elasticsearch\Framework\Indexing\IndexMappingProvider;
use Shopware\Elasticsearch\Framework\Indexing\IndexMappingUpdater;
use Shopware\Elasticsearch\Framework\SystemUpdateListener;
use Shopware\Elasticsearch\Product\ElasticsearchProductDefinition;
use Shopware\Elasticsearch\Product\ElasticsearchProductException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(IndexMappingUpdater::class)]
class IndexMappingUpdaterTest extends TestCase
{
    public function testUpdateWithoutIndexingEnabled(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper->expects($this->once())->method('allowIndexing')->willReturn(false);

        $registry = new ElasticsearchRegistry([
            static::createStub(ElasticsearchProductDefinition::class),
        ]);

        $client = static::createStub(Client::class);
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
            static::createStub(ElasticsearchProductDefinition::class),
        ]);

        $client = static::createStub(Client::class);
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

        $indexMappingProvider = static::createStub(IndexMappingProvider::class);
        $indexMappingProvider
            ->method('build')
            ->willReturn(['foo' => '1']);

        $updater = new IndexMappingUpdater(
            $registry,
            $elasticsearchHelper,
            $client,
            $indexMappingProvider,
            static::createStub(AbstractKeyValueStorage::class),
        );

        $updater->update(Context::createDefaultContext());
    }

    public function testUpdateWithError(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper->method('getIndexName')->willReturn('index');
        $elasticsearchHelper->expects($this->once())->method('allowIndexing')->willReturn(true);

        $definition = static::createStub(ElasticsearchProductDefinition::class);
        $definition
            ->method('getEntityDefinition')
            ->willReturn(new ProductDefinition());

        $registry = new ElasticsearchRegistry([$definition]);

        $client = static::createStub(Client::class);
        $indicesNamespace = $this->createMock(IndicesNamespace::class);
        $indicesNamespace
            ->expects($this->once())
            ->method('putMapping')
            ->with([
                'index' => 'index',
                'body' => [
                    'foo' => '1',
                ],
            ])->willThrowException(new BadRequestHttpException('can\'t merge a non object mapping [completion] with an object mapping'));

        $client
            ->method('indices')
            ->willReturn($indicesNamespace);

        $indexMappingProvider = static::createStub(IndexMappingProvider::class);
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

    public function testUpdatePersistsReindexBeforeRethrowing(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper->method('getIndexName')->willReturn('index');
        $elasticsearchHelper->method('allowIndexing')->willReturn(true);
        $elasticsearchHelper
            ->method('logAndThrowException')
            ->willThrowException(ElasticsearchProductException::cannotChangeFieldType(new BadRequestHttpException('conflict')));

        $definition = static::createStub(ElasticsearchProductDefinition::class);
        $definition
            ->method('getEntityDefinition')
            ->willReturn(new ProductDefinition());

        $indicesNamespace = static::createStub(IndicesNamespace::class);
        $indicesNamespace
            ->method('putMapping')
            ->willThrowException(new BadRequestHttpException('mapper [price.c_b7d2554b0ce847cd82f3ac9bd1c0dfca.gross] cannot be changed from type [float] to [double]'));

        $client = static::createStub(Client::class);
        $client->method('indices')->willReturn($indicesNamespace);

        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage->method('get')->willReturn(['category']);
        $storage->expects($this->once())
            ->method('set')
            ->with(
                SystemUpdateListener::CONFIG_KEY,
                ['category', 'product'],
            );

        $updater = new IndexMappingUpdater(
            new ElasticsearchRegistry([$definition]),
            $elasticsearchHelper,
            $client,
            static::createStub(IndexMappingProvider::class),
            $storage,
        );

        $this->expectException(ElasticsearchProductException::class);

        $updater->update(Context::createDefaultContext());
    }

    public function testUpdateFinishesAllEntitiesBeforeRethrowing(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper->method('allowIndexing')->willReturn(true);
        $elasticsearchHelper
            ->method('getIndexName')
            ->willReturnCallback(static fn (EntityDefinition $definition) => $definition->getEntityName());
        $elasticsearchHelper
            ->method('logAndThrowException')
            ->willReturnCallback(static fn (\Throwable $exception) => throw $exception);

        $product = static::createStub(ElasticsearchProductDefinition::class);
        $product->method('getEntityDefinition')->willReturn(new ProductDefinition());

        $category = static::createStub(ElasticsearchProductDefinition::class);
        $category->method('getEntityDefinition')->willReturn(new CategoryDefinition());

        $indicesNamespace = $this->createMock(IndicesNamespace::class);
        $indicesNamespace
            ->expects($this->exactly(2))
            ->method('putMapping')
            ->willReturnCallback(static function (array $params): array {
                if ($params['index'] === 'product') {
                    throw new BadRequestHttpException('mapper [price] cannot be changed from type [float] to [double]');
                }

                return [];
            });

        $client = static::createStub(Client::class);
        $client->method('indices')->willReturn($indicesNamespace);

        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage->method('get')->willReturn([]);
        $storage->expects($this->once())->method('set')->with(SystemUpdateListener::CONFIG_KEY, ['product']);

        $updater = new IndexMappingUpdater(
            new ElasticsearchRegistry([$product, $category]),
            $elasticsearchHelper,
            $client,
            static::createStub(IndexMappingProvider::class),
            $storage,
        );

        $this->expectException(ElasticsearchProductException::class);

        $updater->update(Context::createDefaultContext());
    }

    public function testUpdateWithMissingIndexError(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper->method('getIndexName')->willReturn('index');
        $elasticsearchHelper->expects($this->once())->method('allowIndexing')->willReturn(true);

        $definition = static::createStub(ElasticsearchProductDefinition::class);
        $definition
            ->method('getEntityDefinition')
            ->willReturn(new ProductDefinition());

        $registry = new ElasticsearchRegistry([$definition]);

        $client = static::createStub(Client::class);
        $indicesNamespace = $this->createMock(IndicesNamespace::class);
        $indicesNamespace
            ->expects($this->once())
            ->method('putMapping')
            ->with([
                'index' => 'index',
                'body' => [
                    'foo' => '1',
                ],
            ])->willThrowException(new NotFoundHttpException('no such index [index]'));

        $client
            ->method('indices')
            ->willReturn($indicesNamespace);

        $indexMappingProvider = static::createStub(IndexMappingProvider::class);
        $indexMappingProvider
            ->method('build')
            ->willReturn(['foo' => '1']);

        $elasticsearchHelper->expects($this->once())->method('logAndThrowException')->with(
            static::callback(static function (NotFoundHttpException $exception) {
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

    public function testUpdateWithObjectNestingChangeError(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper->method('getIndexName')->willReturn('index');
        $elasticsearchHelper->expects($this->once())->method('allowIndexing')->willReturn(true);

        $definition = static::createStub(ElasticsearchProductDefinition::class);
        $definition
            ->method('getEntityDefinition')
            ->willReturn(new ProductDefinition());

        $registry = new ElasticsearchRegistry([$definition]);

        $client = static::createStub(Client::class);
        $indicesNamespace = $this->createMock(IndicesNamespace::class);
        $indicesNamespace
            ->expects($this->once())
            ->method('putMapping')
            ->with([
                'index' => 'index',
                'body' => [
                    'foo' => '1',
                ],
            ])->willThrowException(new BadRequestHttpException('illegal_argument_exception: cannot change object mapping from non-nested to nested'));

        $client
            ->method('indices')
            ->willReturn($indicesNamespace);

        $indexMappingProvider = static::createStub(IndexMappingProvider::class);
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

    public function testUpdateWithConflictedMappingError(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper->method('getIndexName')->willReturn('index');
        $elasticsearchHelper->expects($this->once())->method('allowIndexing')->willReturn(true);

        $definition = static::createStub(ElasticsearchProductDefinition::class);
        $definition
            ->method('getEntityDefinition')
            ->willReturn(new ProductDefinition());

        $registry = new ElasticsearchRegistry([$definition]);

        $client = static::createStub(Client::class);
        $indicesNamespace = $this->createMock(IndicesNamespace::class);
        $indicesNamespace
            ->expects($this->once())
            ->method('putMapping')
            ->with([
                'index' => 'index',
                'body' => [
                    'foo' => '1',
                ],
            ])->willThrowException(new BadRequestHttpException('Mapper for [name.01985ba1826270e4b8ea5da15a05c7bf.search] conflicts with existing mapper:\n\tCannot update parameter [analyzer] from [sw_czech_analyzer] to [sw_whitespace_analyzer].'));

        $client
            ->method('indices')
            ->willReturn($indicesNamespace);

        $indexMappingProvider = static::createStub(IndexMappingProvider::class);
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

    public function testUpdateWithAnalyzerNotConfiguredError(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper->method('getIndexName')->willReturn('index');
        $elasticsearchHelper->expects($this->once())->method('allowIndexing')->willReturn(true);

        $definition = static::createStub(ElasticsearchProductDefinition::class);
        $definition
            ->method('getEntityDefinition')
            ->willReturn(new ProductDefinition());

        $registry = new ElasticsearchRegistry([$definition]);

        $client = static::createStub(Client::class);
        $indicesNamespace = $this->createMock(IndicesNamespace::class);
        $indicesNamespace
            ->expects($this->once())
            ->method('putMapping')
            ->with([
                'index' => 'index',
                'body' => [
                    'foo' => '1',
                ],
            ])->willThrowException(new BadRequestHttpException('mapper_parsing_exception: analyzer [sw_whitespace_technical_term_search_analyzer] has not been configured in mappings'));

        $client
            ->method('indices')
            ->willReturn($indicesNamespace);

        $indexMappingProvider = static::createStub(IndexMappingProvider::class);
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
}
