<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\UpdatedStates;
use Shopware\Core\Content\Product\Events\ProductStatesBeforeChangeEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;

/**
 * @internal
 */
#[CoversClass(ProductStatesBeforeChangeEvent::class)]
class ProductStatesBeforeChangeEventTest extends TestCase
{
    public function testProductStatesBeforeChangeEvent(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);
        $updatedStates = [new UpdatedStates('foobar', ['foo'], ['bar'])];
        $context = Context::createDefaultContext();

        $event = new ProductStatesBeforeChangeEvent($updatedStates, $context);

        static::assertSame($updatedStates, $event->getUpdatedStates());
        static::assertSame($context, $event->getContext());

        $updatedStates = [new UpdatedStates('foobar', ['foo'], ['baz'])];
        $event->setUpdatedStates($updatedStates);

        static::assertSame($updatedStates, $event->getUpdatedStates());
    }
}
