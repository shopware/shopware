<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Event\Listener\PreHydration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Event\Listener\PreHydration\PartialRenderingPreparationSubscriber;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextDependencyAnalyzer;
use Shopware\Core\Content\ContentSystem\Output\ElementTreeUtil;
use Shopware\Core\Content\ContentSystem\Output\PartialRenderer;
use Shopware\Core\Content\ContentSystem\Output\SubTreeExtractor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\ContentElementBuilder;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\EventFactory;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(PartialRenderingPreparationSubscriber::class)]
class PartialRenderingPreparationSubscriberTest extends TestCase
{
    private PartialRenderingPreparationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new PartialRenderingPreparationSubscriber(
            new PartialRenderer(new ElementTreeUtil(), new ContextDependencyAnalyzer(), new SubTreeExtractor())
        );
    }

    #[TestDox('prunes tree when target element ID is set')]
    public function testPrunesTreeWhenTargetElementIdIsSet(): void
    {
        $target = ContentElementBuilder::create('text', 'target-id')->build();
        $sibling = ContentElementBuilder::create('text', 'sibling-id')->build();
        $root = ContentElementBuilder::create('section', 'root-id')
            ->withSlot('default', [$target, $sibling])
            ->build();

        $event = EventFactory::preHydration([$root], targetElementId: 'target-id');
        $this->subscriber->__invoke($event);

        static::assertCount(1, $event->elements);
        static::assertSame('target-id', $event->elements[0]->getId());
    }

    #[TestDox('skips pruning when target element ID is null')]
    public function testSkipsPruningWhenNoTargetElementId(): void
    {
        $element = ContentElementBuilder::create('text', 'e1')->build();

        $event = EventFactory::preHydration([$element]);
        $this->subscriber->__invoke($event);

        static::assertSame([$element], $event->elements);
    }

    #[TestDox('skips pruning when target element ID is empty string')]
    public function testSkipsPruningWhenTargetElementIdIsEmptyString(): void
    {
        $element = ContentElementBuilder::create('text', 'e1')->build();

        $event = EventFactory::preHydration([$element], targetElementId: '');
        $this->subscriber->__invoke($event);

        static::assertSame([$element], $event->elements);
    }
}
