<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Event\Listener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\Event\Listener\ScrollNavigationAnchorListener;
use Shopware\Core\Framework\ContentSystem\Event\RenderedTreeFinalizationEvent;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ScrollNavigationAnchorListener::class)]
class ScrollNavigationAnchorListenerTest extends TestCase
{
    public function testSubscribesToTheFinalizationEvent(): void
    {
        static::assertSame(
            [RenderedTreeFinalizationEvent::class => 'stampAnchors'],
            ScrollNavigationAnchorListener::getSubscribedEvents()
        );
    }

    #[TestDox('stamps the anchor onto nested elements of any type and leaves the rest untouched')]
    public function testStampsAnchorsAtAnyDepth(): void
    {
        $tree = [
            new RenderedElement('root', 'Sw:Grid:Container', ['gap' => 24], [
                'content' => [
                    new RenderedElement('text', 'Sw:Content:Text', ['text' => 'Hello']),
                    new RenderedElement('gallery', 'Sw:Media:Gallery'),
                ],
            ]),
            new RenderedElement('slider', 'Sw:Product:Slider'),
        ];

        $event = $this->createEvent($tree, [
            'scrollNavigation' => [
                'active' => true,
                'position' => 'left',
                'anchors' => [
                    'gallery' => ['label' => 'Pictures'],
                    'slider' => ['label' => ''],
                    'gone' => ['label' => 'Deleted element'],
                ],
            ],
        ]);

        (new ScrollNavigationAnchorListener())->stampAnchors($event);

        $result = $event->tree();

        static::assertArrayNotHasKey('scrollNavigation', $result[0]->properties);
        static::assertSame(['gap' => 24], $result[0]->properties);

        $children = $result[0]->slots['content'];
        static::assertArrayNotHasKey('scrollNavigation', $children[0]->properties);
        static::assertSame(['label' => 'Pictures'], $children[1]->properties['scrollNavigation']);
        static::assertSame(['label' => ''], $result[1]->properties['scrollNavigation']);
    }

    /**
     * @param array<string, mixed> $settings
     */
    #[DataProvider('inertSettingsProvider')]
    #[TestDox('leaves the tree alone when the settings carry no usable anchors: $_dataName')]
    public function testLeavesTheTreeAloneWithoutAnchors(array $settings): void
    {
        $tree = [new RenderedElement('root', 'Sw:Grid:Container')];
        $event = $this->createEvent($tree, $settings);

        (new ScrollNavigationAnchorListener())->stampAnchors($event);

        static::assertSame($tree, $event->tree());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function inertSettingsProvider(): iterable
    {
        yield 'no settings' => [[]];
        yield 'navigation switched off' => [['scrollNavigation' => ['active' => false, 'anchors' => ['root' => ['label' => 'x']]]]];
        yield 'anchors not a map' => [['scrollNavigation' => ['active' => true, 'anchors' => 'root']]];
        yield 'anchor entry not a map' => [['scrollNavigation' => ['active' => true, 'anchors' => ['root' => 'x']]]];
        yield 'anchor for an element that is not in the tree' => [['scrollNavigation' => ['active' => true, 'anchors' => ['other' => ['label' => 'x']]]]];
    }

    /**
     * @param list<RenderedElement> $tree
     * @param array<string, mixed> $settings
     */
    private function createEvent(array $tree, array $settings): RenderedTreeFinalizationEvent
    {
        return new RenderedTreeFinalizationEvent(
            $tree,
            LayoutReference::create('layout-1', 'Test', null, $settings),
            new RenderingSpecification([], PlaceholderValues::from([]), new Request()),
            Generator::generateSalesChannelContext(),
            new RenderingCacheContext(),
        );
    }
}
