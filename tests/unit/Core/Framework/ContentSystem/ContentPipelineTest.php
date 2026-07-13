<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\ContentPipeline;
use Shopware\Core\Framework\ContentSystem\Event\PostHydrationEvent;
use Shopware\Core\Framework\ContentSystem\Event\PreContentHydrationEvent;
use Shopware\Core\Framework\ContentSystem\Hydration\ContentElementHydrator;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\DataContextResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\RenderableLayout;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
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

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->hydrator = new ContentElementHydrator(
            new DataLoaderProvider(new ServiceLocator([])),
            new DataContextResolver(new ContextPathResolver()),
        );
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);
    }

    #[TestDox('dispatches pre- and post-hydration lifecycle events in order in FULL mode')]
    public function testLoadDispatchesLifecycleEventsInFullMode(): void
    {
        $layout = RenderableLayout::fromEntity($this->createLayoutEntity($this->ids->get('layout')));

        $dispatchedEvents = [];
        $this->eventDispatcher->method('dispatch')->willReturnCallback(
            function (object $event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event::class;

                return $event;
            }
        );

        $pipeline = new ContentPipeline($this->hydrator, $this->eventDispatcher);
        $specification = new RenderingSpecification([], PlaceholderValues::from([]), new Request());

        $pipeline->load($layout, $specification, new RenderingCacheContext(), RenderingMode::FULL, Generator::generateSalesChannelContext());

        static::assertSame([PreContentHydrationEvent::class, PostHydrationEvent::class], $dispatchedEvents);
    }

    #[TestDox('hydrates elements in FULL mode')]
    public function testLoadHydratesElementsInFullMode(): void
    {
        $layout = RenderableLayout::fromEntity($this->createLayoutEntity($this->ids->get('layout')));

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $pipeline = new ContentPipeline($this->hydrator, $this->eventDispatcher);
        $specification = new RenderingSpecification([], PlaceholderValues::from([]), new Request());

        $result = $pipeline->load($layout, $specification, new RenderingCacheContext(), RenderingMode::FULL, Generator::generateSalesChannelContext());

        static::assertNotEmpty($result->elements);
    }

    #[TestDox('returns content page with original elements and layout metadata in SKELETON mode')]
    public function testLoadReturnsContentPageInSkeletonMode(): void
    {
        $layoutId = $this->ids->get('layout');
        $layout = RenderableLayout::fromEntity($this->createLayoutEntity($layoutId, 'My Layout'));

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $pipeline = new ContentPipeline($this->hydrator, $this->eventDispatcher);
        $specification = new RenderingSpecification([], PlaceholderValues::from([]), new Request());

        $result = $pipeline->load($layout, $specification, new RenderingCacheContext(), RenderingMode::SKELETON, Generator::generateSalesChannelContext());

        $elements = iterator_to_array($result->elements, false);
        static::assertNotEmpty($elements);
        static::assertSame('section', $elements[0]->getComponent());
        static::assertSame($layoutId, $result->layoutId);
        static::assertSame('My Layout', $result->layoutName);
        static::assertSame('1.0', $result->layoutVersion);
    }

    #[TestDox('renders elements mutated by a PreContentHydration subscriber instead of the original layout')]
    public function testLoadRendersElementsMutatedDuringPreHydration(): void
    {
        $layout = RenderableLayout::fromEntity($this->createLayoutEntity($this->ids->get('layout')));

        $injected = ContentElementBuilder::create('injected', 'injected-id')->build();
        $this->eventDispatcher->method('dispatch')->willReturnCallback(
            function (object $event) use ($injected) {
                if ($event instanceof PreContentHydrationEvent) {
                    $event->elements = [$injected];
                }

                return $event;
            }
        );

        $pipeline = new ContentPipeline($this->hydrator, $this->eventDispatcher);
        $specification = new RenderingSpecification([], PlaceholderValues::from([]), new Request());

        $result = $pipeline->load($layout, $specification, new RenderingCacheContext(), RenderingMode::SKELETON, Generator::generateSalesChannelContext());

        $elements = iterator_to_array($result->elements, false);
        static::assertCount(1, $elements);
        static::assertSame('injected-id', $elements[0]->getId());
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
