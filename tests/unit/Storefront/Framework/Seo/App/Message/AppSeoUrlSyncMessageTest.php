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

    public function testTheMessageDefaultsToAStaticSyncOfAllApps(): void
    {
        $message = new AppSeoUrlSyncMessage();

        static::assertNull($message->getAppId());
        static::assertFalse($message->shouldRegenerateEntityRoutes());
    }

    public function testTheMessageCarriesTheAppAndTheRegenerationRequest(): void
    {
        $message = new AppSeoUrlSyncMessage(self::APP_ID, true);

        static::assertSame(self::APP_ID, $message->getAppId());
        static::assertTrue($message->shouldRegenerateEntityRoutes());
    }

    public function testAStaticSyncIsNotDeduplicatedAgainstAFullSync(): void
    {
        static::assertNotSame(
            (new AppSeoUrlSyncMessage(self::APP_ID))->deduplicationId(),
            (new AppSeoUrlSyncMessage(self::APP_ID, true))->deduplicationId()
        );
    }

    public function testTheSyncOfOneAppIsNotDeduplicatedAgainstAnother(): void
    {
        static::assertNotSame(
            (new AppSeoUrlSyncMessage(self::APP_ID))->deduplicationId(),
            (new AppSeoUrlSyncMessage('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'))->deduplicationId()
        );

        static::assertNotSame(
            (new AppSeoUrlSyncMessage(self::APP_ID))->deduplicationId(),
            (new AppSeoUrlSyncMessage())->deduplicationId()
        );
    }

    public function testTwoIdenticalRequestsShareTheirDeduplicationId(): void
    {
        static::assertSame(
            (new AppSeoUrlSyncMessage(self::APP_ID, true))->deduplicationId(),
            (new AppSeoUrlSyncMessage(self::APP_ID, true))->deduplicationId()
        );
    }
}
