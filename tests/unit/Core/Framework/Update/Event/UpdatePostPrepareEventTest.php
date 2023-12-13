<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Update\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Update\Event\UpdatePostPrepareEvent;

/**
 * @internal
 */
#[CoversClass(UpdatePostPrepareEvent::class)]
class UpdatePostPrepareEventTest extends TestCase
{
    public function testGetters(): void
    {
        $context = Context::createDefaultContext();
        $event = new UpdatePostPrepareEvent($context, 'currentVersion', 'newVersion');

        static::assertSame('currentVersion', $event->getCurrentVersion());
        static::assertSame('newVersion', $event->getNewVersion());
        static::assertSame($context, $event->getContext());
    }
}
