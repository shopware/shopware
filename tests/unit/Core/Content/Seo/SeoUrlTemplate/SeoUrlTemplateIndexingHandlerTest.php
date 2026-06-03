<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SeoUrlTemplate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoUrlTemplateIndexingHandler::class)]
class SeoUrlTemplateIndexingHandlerTest extends TestCase
{
    public function testIteratesEntitiesInBatchesAndUpdatesSeoUrls(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $id3 = Uuid::randomHex();

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('isVersionAware')->willReturn(true);

        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definitionRegistry->method('has')->willReturn(true);
        $definitionRegistry->method('getByEntityName')->willReturn($definition);

        $seoUrlRouteRegistry = static::createStub(SeoUrlRouteRegistry::class);
        $seoUrlRouteRegistry->method('findByRouteName')
            ->willReturn(static::createStub(SeoUrlRouteInterface::class));

        // fetch() returns [binaryId => hexId] batches and an empty array stops iteration.
        $iterator = static::createStub(IterableQuery::class);
        $iterator->method('fetch')->willReturnOnConsecutiveCalls(
            ['binA' => $id1, 'binB' => $id2],
            ['binC' => $id3],
            []
        );

        $iteratorFactory = $this->createMock(IteratorFactory::class);
        $iteratorFactory->expects($this->once())
            ->method('createIterator')
            ->with($definition, null, 250, Defaults::LIVE_VERSION)
            ->willReturn($iterator);

        $captured = [];
        $seoUrlUpdater = $this->createMock(SeoUrlUpdater::class);
        $seoUrlUpdater->expects($this->exactly(2))
            ->method('update')
            ->willReturnCallback(function (string $route, array $ids) use (&$captured): void {
                $captured[] = [$route, $ids];
            });

        $handler = new SeoUrlTemplateIndexingHandler($seoUrlUpdater, $iteratorFactory, $definitionRegistry, $seoUrlRouteRegistry);
        $handler->__invoke(new SeoUrlTemplateIndexingMessage('frontend.navigation.page', 'category'));

        static::assertSame([
            ['frontend.navigation.page', [$id1, $id2]],
            ['frontend.navigation.page', [$id3]],
        ], $captured);
    }

    public function testPassesNullVersionForNonVersionAwareDefinition(): void
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('isVersionAware')->willReturn(false);

        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definitionRegistry->method('has')->willReturn(true);
        $definitionRegistry->method('getByEntityName')->willReturn($definition);

        $seoUrlRouteRegistry = static::createStub(SeoUrlRouteRegistry::class);
        $seoUrlRouteRegistry->method('findByRouteName')
            ->willReturn(static::createStub(SeoUrlRouteInterface::class));

        $iterator = static::createStub(IterableQuery::class);
        $iterator->method('fetch')->willReturn([]);

        $iteratorFactory = $this->createMock(IteratorFactory::class);
        $iteratorFactory->expects($this->once())
            ->method('createIterator')
            ->with($definition, null, 250, null)
            ->willReturn($iterator);

        $seoUrlUpdater = $this->createMock(SeoUrlUpdater::class);
        $seoUrlUpdater->expects($this->never())->method('update');

        $handler = new SeoUrlTemplateIndexingHandler($seoUrlUpdater, $iteratorFactory, $definitionRegistry, $seoUrlRouteRegistry);
        $handler->__invoke(new SeoUrlTemplateIndexingMessage('frontend.detail.page', 'product'));
    }

    public function testReturnsEarlyOnEmptyRouteName(): void
    {
        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $definitionRegistry->expects($this->never())->method('has');
        $iteratorFactory = $this->createMock(IteratorFactory::class);
        $iteratorFactory->expects($this->never())->method('createIterator');
        $seoUrlUpdater = $this->createMock(SeoUrlUpdater::class);
        $seoUrlUpdater->expects($this->never())->method('update');

        $handler = new SeoUrlTemplateIndexingHandler($seoUrlUpdater, $iteratorFactory, $definitionRegistry, static::createStub(SeoUrlRouteRegistry::class));
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

        $handler = new SeoUrlTemplateIndexingHandler($seoUrlUpdater, $iteratorFactory, $definitionRegistry, static::createStub(SeoUrlRouteRegistry::class));
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

        $handler = new SeoUrlTemplateIndexingHandler($seoUrlUpdater, $iteratorFactory, $definitionRegistry, static::createStub(SeoUrlRouteRegistry::class));
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

        $handler = new SeoUrlTemplateIndexingHandler($seoUrlUpdater, $iteratorFactory, $definitionRegistry, $seoUrlRouteRegistry);
        $handler->__invoke(new SeoUrlTemplateIndexingMessage('unregistered.route', 'category'));
    }
}
