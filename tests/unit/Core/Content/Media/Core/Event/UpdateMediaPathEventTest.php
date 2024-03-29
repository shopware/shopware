<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Core\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Core\Event\UpdateMediaPathEvent;

/**
 * @internal
 */
#[CoversClass(UpdateMediaPathEvent::class)]
class UpdateMediaPathEventTest extends TestCase
{
    public function testGetIterator(): void
    {
        $event = new UpdateMediaPathEvent(['foo', 'bar']);

        static::assertSame(['foo', 'bar'], iterator_to_array($event->getIterator()));
    }
}
