<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Shopware\Core\Content\Media\Subscriber\CustomFieldsUnusedMediaSubscriber;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Media\Subscriber\CustomFieldsUnusedMediaSubscriber
 */
#[Package('buyers-experience')]
class CustomFieldsUnusedMediaSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertEquals(
            [
                UnusedMediaSearchEvent::class => 'removeUsedMedia',
            ],
            CustomFieldsUnusedMediaSubscriber::getSubscribedEvents()
        );
    }
}
