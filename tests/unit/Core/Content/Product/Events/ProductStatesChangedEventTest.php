<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\UpdatedStates;
use Shopware\Core\Content\Product\Events\ProductStatesChangedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;

/**
 * @internal
 */
#[CoversClass(ProductStatesChangedEvent::class)]
class ProductStatesChangedEventTest extends TestCase
{
    public function testProductStatesChangedEvent(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $updatedStates = [new UpdatedStates('foobar', ['foo'], ['bar'])];
        $context = Context::createDefaultContext();

        $event = new ProductStatesChangedEvent($updatedStates, $context);

        static::assertSame($updatedStates, $event->getUpdatedStates());
        static::assertSame($context, $event->getContext());
    }
}
