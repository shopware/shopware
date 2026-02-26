<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Content\ContentSystem\ContentPipeline;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Event\PostHydrationEvent;
use Shopware\Core\Content\ContentSystem\Event\PreContentHydrationEvent;
use Shopware\Core\Content\ContentSystem\Hydration\ContentElementHydrator;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\DataContextResolver;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Content\ContentSystem\PlaceholderValues;
use Shopware\Core\Content\ContentSystem\RenderingMode;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\ContentElementBuilder;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ContentPipeline::class)]
class ContentPipelineTest extends TestCase
{
    private ContentElementHydrator $hydrator;

    private EventDispatcherInterface&Stub $eventDispatcher;

    protected function setUp(): void
    {
        $this->hydrator = new ContentElementHydrator(
            new DataLoaderProvider(new ServiceLocator([])),
            new DataContextResolver(new ContextPathResolver()),
        );
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);
    }

    #[TestDox('dispatches pre and post hydration events and returns hydrated elements in FULL mode')]
    public function testLoadDispatchesEventsAndReturnsHydratedElementsInFullMode(): void
    {
        $layoutId = Uuid::randomHex();
        $layoutEntity = $this->createLayoutEntity($layoutId);

        $repository = $this->createLayoutRepository($layoutEntity);

        $dispatchedEvents = [];
        $this->eventDispatcher->method('dispatch')->willReturnCallback(
            function (object $event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event::class;

                return $event;
            }
        );

        $pipeline = new ContentPipeline($repository, $this->hydrator, $this->eventDispatcher);
        $specification = new RenderingSpecification($layoutId, [], PlaceholderValues::from([]), new Request());

        $result = $pipeline->load($specification, new RenderingCacheContext(), RenderingMode::FULL, Generator::generateSalesChannelContext());

        static::assertSame([PreContentHydrationEvent::class, PostHydrationEvent::class], $dispatchedEvents);
        static::assertNotEmpty($result->elements);
    }

    #[TestDox('returns content page with original elements and layout metadata in SKELETON mode')]
    public function testLoadReturnsContentPageInSkeletonMode(): void
    {
        $layoutId = Uuid::randomHex();
        $layoutEntity = $this->createLayoutEntity($layoutId, 'My Layout');

        $repository = $this->createLayoutRepository($layoutEntity);

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $pipeline = new ContentPipeline($repository, $this->hydrator, $this->eventDispatcher);
        $specification = new RenderingSpecification($layoutId, [], PlaceholderValues::from([]), new Request());

        $result = $pipeline->load($specification, new RenderingCacheContext(), RenderingMode::SKELETON, Generator::generateSalesChannelContext());

        $elements = iterator_to_array($result->elements, false);
        static::assertNotEmpty($elements);
        static::assertSame('section', $elements[0]->getComponent());
        static::assertSame($layoutId, $result->layoutId);
        static::assertSame('My Layout', $result->layoutName);
    }

    #[TestDox('throws layout not found when layout does not exist')]
    public function testLoadThrowsLayoutNotFoundWhenLayoutDoesNotExist(): void
    {
        $layoutId = Uuid::randomHex();

        $repository = $this->createLayoutRepository();

        $pipeline = new ContentPipeline($repository, $this->hydrator, $this->eventDispatcher);
        $specification = new RenderingSpecification($layoutId, [], PlaceholderValues::from([]), new Request());

        $this->expectExceptionObject(ContentSystemException::layoutNotFound($layoutId));

        $pipeline->load($specification, new RenderingCacheContext(), RenderingMode::FULL, Generator::generateSalesChannelContext());
    }

    /**
     * @return StaticEntityRepository<ContentLayoutCollection>
     */
    private function createLayoutRepository(ContentLayoutEntity ...$entities): StaticEntityRepository
    {
        /** @var StaticEntityRepository<ContentLayoutCollection> $repository */
        $repository = new StaticEntityRepository([$entities]);

        return $repository;
    }

    private function createLayoutEntity(string $layoutId, string $name = 'Test Layout'): ContentLayoutEntity
    {
        $element = ContentElementBuilder::create('section')->build();
        $entity = new ContentLayoutEntity();
        $entity->setId($layoutId);
        $entity->setName($name);
        $entity->setVersion('1.0');
        $entity->setLayout([$element]);

        return $entity;
    }
}
