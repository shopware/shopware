<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Subscriber\UnusedMediaSubscriber;
use Shopware\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Cms\Subscriber\UnusedMediaSubscriber
 */
#[Package('buyers-experience')]
class UnusedMediaSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertEquals(
            [
                UnusedMediaSearchEvent::class => 'removeUsedMedia',
            ],
            UnusedMediaSubscriber::getSubscribedEvents()
        );
    }
}
