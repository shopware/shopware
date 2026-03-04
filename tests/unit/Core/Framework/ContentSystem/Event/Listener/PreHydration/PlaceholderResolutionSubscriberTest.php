<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Event\Listener\PreHydration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Event\Listener\PreHydration\PlaceholderResolutionSubscriber;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Tests\Unit\Core\Framework\ContentSystem\_helper\ContentElementBuilder;
use Shopware\Tests\Unit\Core\Framework\ContentSystem\_helper\EventFactory;

/**
 * @internal
 */
#[CoversClass(PlaceholderResolutionSubscriber::class)]
class PlaceholderResolutionSubscriberTest extends TestCase
{
    #[TestDox('replaces placeholders in all elements using specification values')]
    public function testReplacesPlaceholdersInAllElements(): void
    {
        $element1 = ContentElementBuilder::create('text', 'e1')
            ->withProperty('title', '{{productId}}')
            ->build();

        $element2 = ContentElementBuilder::create('text', 'e2')
            ->withProperty('label', 'ID: {{productId}}')
            ->build();

        $event = EventFactory::preHydration(
            [$element1, $element2],
            placeholderValues: PlaceholderValues::from(['productId' => 'abc123']),
        );

        $subscriber = new PlaceholderResolutionSubscriber();
        $subscriber->__invoke($event);

        static::assertSame('abc123', $element1->getProperty('title'));
        static::assertSame('ID: abc123', $element2->getProperty('label'));
    }

    #[TestDox('handles empty elements array without errors')]
    public function testHandlesEmptyElementsArray(): void
    {
        $event = EventFactory::preHydration(
            [],
            [],
            null,
            PlaceholderValues::from(['key' => 'value']),
        );

        $subscriber = new PlaceholderResolutionSubscriber();
        $subscriber->__invoke($event);
        static::assertSame([], $event->elements);
    }
}
