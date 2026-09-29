<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Update\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Event\UpdateEvent;
use Shopware\Core\Framework\Update\Event\UpdatePreFinishEvent;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UpdatePreFinishEvent::class)]
#[CoversClass(UpdateEvent::class)]
class UpdatePreFinishEventTest extends TestCase
{
    public function testGetters(): void
    {
        $context = Context::createDefaultContext();
        $event = new UpdatePreFinishEvent($context, 'oldVersion', 'newVersion');

        static::assertSame('oldVersion', $event->getOldVersion());
        static::assertSame('newVersion', $event->getNewVersion());
        static::assertSame($context, $event->getContext());
    }
}
