<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Path\Contract\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Path\Contract\Event\UpdateMediaPathEvent;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Media\Path\Contract\Event\UpdateMediaPathEvent
 */
class UpdateMediaPathEventTest extends TestCase
{
    public function testGetIterator(): void
    {
        $event = new UpdateMediaPathEvent(['foo', 'bar']);

        static::assertSame(['foo', 'bar'], iterator_to_array($event->getIterator()));
    }
}
