<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Event\Listener\PreHydration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWithJson;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Event\Listener\PreHydration\PartialRenderingPreparationSubscriber;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDependencyAnalyzer;
use Shopware\Core\Framework\ContentSystem\Output\ElementTreeUtil;
use Shopware\Core\Framework\ContentSystem\Output\PartialRenderer;
use Shopware\Core\Framework\ContentSystem\Output\SubTreeExtractor;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\EventFactory;

/**
 * @internal
 */
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

    #[TestWithJson('[null]')]
    #[TestWithJson('[""]')]
    #[TestDox('skips pruning when target element ID is $targetElementId')]
    public function testSkipsPruningWhenTargetElementIdIsNotSet(?string $targetElementId): void
    {
        $element = ContentElementBuilder::create('text', 'e1')->build();

        $event = EventFactory::preHydration([$element], targetElementId: $targetElementId);
        $this->subscriber->__invoke($event);

        static::assertSame([$element], $event->elements);
    }
}
