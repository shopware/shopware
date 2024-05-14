<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Shopware\Core\Content\Media\Subscriber\CustomFieldsUnusedMediaSubscriber;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(CustomFieldsUnusedMediaSubscriber::class)]
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
