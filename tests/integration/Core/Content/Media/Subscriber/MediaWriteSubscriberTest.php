<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\MediaIndexerEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;

/**
 * @internal
 */
#[Package('discovery')]
class MediaWriteSubscriberTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testMovingAndDeletingAThumbnailInvalidatesItsOwners(): void
    {
        $context = Context::createDefaultContext();
        $previous = Uuid::randomHex();
        $next = Uuid::randomHex();
        $thumbnail = Uuid::randomHex();
        static::getContainer()->get('media.repository')->create([
            ['id' => $previous, 'thumbnails' => [[
                'id' => $thumbnail,
                'width' => 220,
                'height' => 110,
                'mediaThumbnailSize' => ['width' => 220, 'height' => 110],
            ]]],
            ['id' => $next],
        ], $context);

        $notifications = [];
        EventHookDispatcher::fromContainer(static::getContainer())->on(MediaIndexerEvent::class, static function (MediaIndexerEvent $event) use (&$notifications): void {
            $notifications[] = $event->getIds();
        });

        $repository = static::getContainer()->get('media_thumbnail.repository');
        $repository->update([['id' => $thumbnail, 'mediaId' => $next]], $context);
        $repository->delete([['id' => $thumbnail]], $context);

        static::assertCount(2, $notifications);
        static::assertEqualsCanonicalizing([$previous, $next], $notifications[0]);
        static::assertSame([$next], $notifications[1]);
    }
}
