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
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
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

    #[TestDox('hydrates elements and dispatches lifecycle events in FULL mode')]
    public function testLoadHydratesElementsInFullMode(): void
    {
        $layout = RenderableLayout::fromEntity($this->createLayoutEntity(Uuid::randomHex()));

        $dispatchedEvents = [];
        $this->eventDispatcher->method('dispatch')->willReturnCallback(
            function (object $event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event::class;

                return $event;
            }
        );

        $pipeline = new ContentPipeline($this->hydrator, $this->eventDispatcher);
        $specification = new RenderingSpecification([], PlaceholderValues::from([]), new Request());

        $result = $pipeline->load($layout, $specification, new RenderingCacheContext(), RenderingMode::FULL, Generator::generateSalesChannelContext());

        static::assertSame([PreContentHydrationEvent::class, PostHydrationEvent::class], $dispatchedEvents);
        static::assertNotEmpty($result->elements);
    }

    #[TestDox('returns content page with original elements and layout metadata in SKELETON mode')]
    public function testLoadReturnsContentPageInSkeletonMode(): void
    {
        $layoutId = Uuid::randomHex();
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
