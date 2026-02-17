<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Event\Listener\PostHydration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Event\Listener\PostHydration\VirtualRootCleanupSubscriber;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\LanguageLoader\LanguageLoaderConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Content\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Content\ContentSystem\PlaceholderValues;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\ContentElementBuilder;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\EventFactory;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(VirtualRootCleanupSubscriber::class)]
class VirtualRootCleanupSubscriberTest extends TestCase
{
    private VirtualRootWrapper $wrapper;

    private VirtualRootCleanupSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->wrapper = new VirtualRootWrapper();
        $this->subscriber = new VirtualRootCleanupSubscriber($this->wrapper);
    }

    #[TestDox('unwraps virtual root after hydration, restoring original elements')]
    public function testUnwrapsVirtualRootAfterHydration(): void
    {
        $root1 = ContentElementBuilder::create('text', 'r1')->build();
        $root2 = ContentElementBuilder::create('image', 'r2')->build();

        $requirement = new DataRequirement('language', 'language', new LanguageLoaderConfig());
        $specification = new RenderingSpecification(
            'layout-1',
            [$requirement],
            PlaceholderValues::from([]),
            new Request(),
        );

        $virtualRoot = $this->wrapper->wrap([$root1, $root2], $specification);
        $event = EventFactory::postHydration([$virtualRoot], [$requirement]);

        $this->subscriber->__invoke($event);

        static::assertCount(2, $event->elements);
        static::assertSame('r1', $event->elements[0]->getId());
        static::assertSame('r2', $event->elements[1]->getId());
    }

    #[TestDox('skips cleanup when wrapping is not required')]
    public function testSkipsCleanupWhenNoVirtualRoot(): void
    {
        $element = ContentElementBuilder::create('text', 'e1')->build();

        $event = EventFactory::postHydration([$element], []);

        $this->subscriber->__invoke($event);

        static::assertCount(1, $event->elements);
        static::assertSame($element, $event->elements[0]);
    }

    #[TestDox('skips cleanup gracefully when virtual root was pruned away during partial rendering')]
    public function testSkipsCleanupWhenVirtualRootPrunedAway(): void
    {
        $requirement = new DataRequirement('language', 'language', new LanguageLoaderConfig());
        $regularElement = ContentElementBuilder::create('text', 'e1')->build();

        $event = EventFactory::postHydration([$regularElement], [$requirement]);

        $this->subscriber->__invoke($event);

        static::assertCount(1, $event->elements);
        static::assertSame($regularElement, $event->elements[0]);
    }
}
