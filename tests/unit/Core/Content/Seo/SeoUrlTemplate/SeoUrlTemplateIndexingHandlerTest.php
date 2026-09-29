<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SeoUrlTemplate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntitySeoUrlRouteInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateIndexingHandler;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateIndexingMessage;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoUrlTemplateIndexingHandler::class)]
class SeoUrlTemplateIndexingHandlerTest extends TestCase
{
    private const BATCH_SIZE = 250;

    public function testProcessesFullBatchAndChainsFollowUpMessage(): void
    {
        $ids = $this->randomHexIds(self::BATCH_SIZE);

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('isVersionAware')->willReturn(true);

        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definitionRegistry->method('has')->willReturn(true);
        $definitionRegistry->method('getByEntityName')->willReturn($definition);

        $seoUrlRouteRegistry = static::createStub(SeoUrlRouteRegistry::class);
        $seoUrlRouteRegistry->method('findByRouteName')
            ->willReturn(static::createStub(SeoUrlRouteInterface::class));

        $iterator = static::createStub(IterableQuery::class);
        $iterator->method('fetch')->willReturn(array_combine($ids, $ids));
        $iterator->method('getOffset')->willReturn(['offset' => 4711]);

        $iteratorFactory = $this->createMock(IteratorFactory::class);
        $iteratorFactory->expects($this->once())
            ->method('createIterator')
            ->with($definition, null, self::BATCH_SIZE, Defaults::LIVE_VERSION)
            ->willReturn($iterator);

        $seoUrlUpdater = $this->createMock(SeoUrlUpdater::class);
        $seoUrlUpdater->expects($this->once())
            ->method('update')
            ->with('frontend.navigation.page', $ids);

        // A full batch means more entities may follow: the handler must chain a
        // follow-up message carrying the iterator offset.
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(static fn (SeoUrlTemplateIndexingMessage $message): bool => $message->routeName === 'frontend.navigation.page'
                && $message->entityName === 'category'
                && $message->offset === ['offset' => 4711]))
            ->willReturn(new Envelope(new \stdClass()));

        $handler = $this->createHandler($seoUrlUpdater, $iteratorFactory, $definitionRegistry, $seoUrlRouteRegistry, $messageBus);
        $handler->__invoke(new SeoUrlTemplateIndexingMessage('frontend.navigation.page', 'category'));
    }

    public function testStopsChainOnPartialBatch(): void
    {
        $ids = $this->randomHexIds(2);

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('isVersionAware')->willReturn(true);

        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definitionRegistry->method('has')->willReturn(true);
        $definitionRegistry->method('getByEntityName')->willReturn($definition);

        $seoUrlRouteRegistry = static::createStub(SeoUrlRouteRegistry::class);
        $seoUrlRouteRegistry->method('findByRouteName')
            ->willReturn(static::createStub(SeoUrlRouteInterface::class));

        $iterator = static::createStub(IterableQuery::class);
        $iterator->method('fetch')->willReturn(array_combine($ids, $ids));

        $iteratorFactory = $this->createMock(IteratorFactory::class);
        $iteratorFactory->expects($this->once())
            ->method('createIterator')
            ->willReturn($iterator);

        $seoUrlUpdater = $this->createMock(SeoUrlUpdater::class);
        $seoUrlUpdater->expects($this->once())
            ->method('update')
            ->with('frontend.navigation.page', $ids);

        // Fewer ids than the batch size: the entity set is exhausted.
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $handler = $this->createHandler($seoUrlUpdater, $iteratorFactory, $definitionRegistry, $seoUrlRouteRegistry, $messageBus);
        $handler->__invoke(new SeoUrlTemplateIndexingMessage('frontend.navigation.page', 'category'));
    }

    public function testPassesMessageOffsetToIteratorAndStopsOnEmptyBatch(): void
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('isVersionAware')->willReturn(false);

        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definitionRegistry->method('has')->willReturn(true);
        $definitionRegistry->method('getByEntityName')->willReturn($definition);

        $seoUrlRouteRegistry = static::createStub(SeoUrlRouteRegistry::class);
        $seoUrlRouteRegistry->method('findByRouteName')
            ->willReturn(static::createStub(SeoUrlRouteInterface::class));

        // A chained message resumes where the previous batch stopped.
        $iterator = static::createStub(IterableQuery::class);
        $iterator->method('fetch')->willReturn([]);

        $iteratorFactory = $this->createMock(IteratorFactory::class);
        $iteratorFactory->expects($this->once())
            ->method('createIterator')
            ->with($definition, ['offset' => 4711], self::BATCH_SIZE, null)
            ->willReturn($iterator);

        $seoUrlUpdater = $this->createMock(SeoUrlUpdater::class);
        $seoUrlUpdater->expects($this->never())->method('update');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $handler = $this->createHandler($seoUrlUpdater, $iteratorFactory, $definitionRegistry, $seoUrlRouteRegistry, $messageBus);
        $handler->__invoke(new SeoUrlTemplateIndexingMessage('frontend.detail.page', 'product', ['offset' => 4711]));
    }

    public function testReturnsEarlyOnEmptyRouteName(): void
    {
        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $definitionRegistry->expects($this->never())->method('has');
        $iteratorFactory = $this->createMock(IteratorFactory::class);
        $iteratorFactory->expects($this->never())->method('createIterator');
        $seoUrlUpdater = $this->createMock(SeoUrlUpdater::class);
        $seoUrlUpdater->expects($this->never())->method('update');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $handler = $this->createHandler($seoUrlUpdater, $iteratorFactory, $definitionRegistry, static::createStub(SeoUrlRouteRegistry::class), $messageBus);
        $handler->__invoke(new SeoUrlTemplateIndexingMessage('', 'category'));
    }

    public function testReturnsEarlyOnEmptyEntityName(): void
    {
        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $definitionRegistry->expects($this->never())->method('has');
        $iteratorFactory = $this->createMock(IteratorFactory::class);
        $iteratorFactory->expects($this->never())->method('createIterator');
        $seoUrlUpdater = $this->createMock(SeoUrlUpdater::class);
        $seoUrlUpdater->expects($this->never())->method('update');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $handler = $this->createHandler($seoUrlUpdater, $iteratorFactory, $definitionRegistry, static::createStub(SeoUrlRouteRegistry::class), $messageBus);
        $handler->__invoke(new SeoUrlTemplateIndexingMessage('frontend.navigation.page', ''));
    }

    public function testSkipsUnknownEntity(): void
    {
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definitionRegistry->method('has')->willReturn(false);

        $iteratorFactory = $this->createMock(IteratorFactory::class);
        $iteratorFactory->expects($this->never())->method('createIterator');
        $seoUrlUpdater = $this->createMock(SeoUrlUpdater::class);
        $seoUrlUpdater->expects($this->never())->method('update');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $handler = $this->createHandler($seoUrlUpdater, $iteratorFactory, $definitionRegistry, static::createStub(SeoUrlRouteRegistry::class), $messageBus);
        $handler->__invoke(new SeoUrlTemplateIndexingMessage('frontend.navigation.page', 'unknown'));
    }

    public function testSkipsUnregisteredRoute(): void
    {
        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $definitionRegistry->expects($this->once())->method('has')->willReturn(true);
        $definitionRegistry->expects($this->never())->method('getByEntityName');

        $seoUrlRouteRegistry = static::createStub(SeoUrlRouteRegistry::class);
        $seoUrlRouteRegistry->method('findByRouteName')->willReturn(null);

        $iteratorFactory = $this->createMock(IteratorFactory::class);
        $iteratorFactory->expects($this->never())->method('createIterator');
        $seoUrlUpdater = $this->createMock(SeoUrlUpdater::class);
        $seoUrlUpdater->expects($this->never())->method('update');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $handler = $this->createHandler($seoUrlUpdater, $iteratorFactory, $definitionRegistry, $seoUrlRouteRegistry, $messageBus);
        $handler->__invoke(new SeoUrlTemplateIndexingMessage('unregistered.route', 'category'));
    }

    public function testProcessesHeadlessStoreApiRouteNotInTheRouteRegistry(): void
    {
        // Headless store-api routes are tagged `shopware.entity.seo_url.route` and are
        // absent from the SeoUrlRouteRegistry, but their templates are equally editable,
        // so the reindex must run for them too.
        $ids = $this->randomHexIds(2);

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('isVersionAware')->willReturn(true);

        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definitionRegistry->method('has')->willReturn(true);
        $definitionRegistry->method('getByEntityName')->willReturn($definition);

        $seoUrlRouteRegistry = static::createStub(SeoUrlRouteRegistry::class);
        $seoUrlRouteRegistry->method('findByRouteName')->willReturn(null);

        $iterator = static::createStub(IterableQuery::class);
        $iterator->method('fetch')->willReturn(array_combine($ids, $ids));

        $iteratorFactory = $this->createMock(IteratorFactory::class);
        $iteratorFactory->expects($this->once())->method('createIterator')->willReturn($iterator);

        $seoUrlUpdater = $this->createMock(SeoUrlUpdater::class);
        $seoUrlUpdater->expects($this->once())
            ->method('update')
            ->with('store-api.product.detail', $ids);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $handler = $this->createHandler(
            $seoUrlUpdater,
            $iteratorFactory,
            $definitionRegistry,
            $seoUrlRouteRegistry,
            $messageBus,
            [$this->entitySeoUrlRoute('store-api.product.detail', 'product')]
        );
        $handler->__invoke(new SeoUrlTemplateIndexingMessage('store-api.product.detail', 'product'));
    }

    /**
     * @param iterable<EntitySeoUrlRouteInterface> $entitySeoUrlRoutes
     */
    private function createHandler(
        SeoUrlUpdater $seoUrlUpdater,
        IteratorFactory $iteratorFactory,
        DefinitionInstanceRegistry $definitionRegistry,
        SeoUrlRouteRegistry $seoUrlRouteRegistry,
        MessageBusInterface $messageBus,
        iterable $entitySeoUrlRoutes = [],
    ): SeoUrlTemplateIndexingHandler {
        return new SeoUrlTemplateIndexingHandler(
            $seoUrlUpdater,
            $iteratorFactory,
            $definitionRegistry,
            $seoUrlRouteRegistry,
            $messageBus,
            $entitySeoUrlRoutes
        );
    }

    private function entitySeoUrlRoute(string $routeName, string $entityName): EntitySeoUrlRouteInterface
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($entityName);

        $route = static::createStub(EntitySeoUrlRouteInterface::class);
        $route->method('getConfig')->willReturn(
            new SeoUrlRouteConfig($definition, $routeName, '{{ id }}')
        );

        return $route;
    }

    /**
     * @return list<string>
     */
    private function randomHexIds(int $count): array
    {
        return array_map(static fn (): string => Uuid::randomHex(), range(1, $count));
    }
}
