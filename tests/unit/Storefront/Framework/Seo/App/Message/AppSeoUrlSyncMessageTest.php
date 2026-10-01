<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\App\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Seo\App\Message\AppSeoUrlSyncMessage;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AppSeoUrlSyncMessage::class)]
class AppSeoUrlSyncMessageTest extends TestCase
{
    private const APP_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const OTHER_APP_ID = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function testWithoutAnAppTheMessageSynchronisesAllApps(): void
    {
        $message = new AppSeoUrlSyncMessage();

        static::assertNull($message->getAppId());
        static::assertSame('all', $message->deduplicationId());
    }

    public function testTheMessageCarriesTheAppToSynchronise(): void
    {
        $message = new AppSeoUrlSyncMessage(self::APP_ID);

        static::assertSame(self::APP_ID, $message->getAppId());
        static::assertSame(self::APP_ID, $message->deduplicationId());
    }

    public function testTheSyncOfOneAppIsNotDeduplicatedAgainstAnotherAppOrAllApps(): void
    {
        $deduplicationId = (new AppSeoUrlSyncMessage(self::APP_ID))->deduplicationId();

        static::assertNotSame($deduplicationId, (new AppSeoUrlSyncMessage(self::OTHER_APP_ID))->deduplicationId());
        static::assertNotSame($deduplicationId, (new AppSeoUrlSyncMessage())->deduplicationId());
    }
}
