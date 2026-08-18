<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\Event\ContentTreePreparationEvent;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentTreePreparationEvent::class)]
class ContentTreePreparationEventTest extends TestCase
{
    #[TestDox('returns the forest it was constructed with')]
    public function testTreeReturnsTheConstructedForest(): void
    {
        $tree = [new StoredElement('root-id', 'section')];

        static::assertSame($tree, $this->createEvent($tree)->tree());
    }

    #[TestDox('returns the replacement forest after replaceTree')]
    public function testReplaceTreeReplacesWhatTreeReturns(): void
    {
        $event = $this->createEvent([new StoredElement('root-id', 'section')]);
        $replacement = [new StoredElement('injected-id', 'text')];

        $event->replaceTree($replacement);

        static::assertSame($replacement, $event->tree());
    }

    #[TestDox('exposes the layout reference, specification, sales channel context and cache context')]
    public function testEventExposesItsRenderingMetadata(): void
    {
        $layout = LayoutReference::create('layout-1', 'Test', '1.0');
        $specification = new RenderingSpecification([], PlaceholderValues::from([]), new Request());
        $salesChannelContext = Generator::generateSalesChannelContext();
        $cacheContext = new RenderingCacheContext();

        $event = new ContentTreePreparationEvent([], $layout, $specification, $salesChannelContext, $cacheContext);

        static::assertSame($layout, $event->layout);
        static::assertSame($specification, $event->specification);
        static::assertSame($salesChannelContext, $event->salesChannelContext);
        static::assertSame($salesChannelContext, $event->getSalesChannelContext());
        static::assertSame($salesChannelContext->getContext(), $event->getContext());
        static::assertSame($cacheContext, $event->cacheContext);
    }

    /**
     * @param list<StoredElement> $tree
     */
    private function createEvent(array $tree): ContentTreePreparationEvent
    {
        return new ContentTreePreparationEvent(
            $tree,
            LayoutReference::create('layout-1', 'Test', null),
            new RenderingSpecification([], PlaceholderValues::from([]), new Request()),
            Generator::generateSalesChannelContext(),
            new RenderingCacheContext(),
        );
    }
}
