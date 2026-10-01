<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlDomainListener;
use Shopware\Storefront\Framework\Seo\App\Message\AppSeoUrlSyncMessage;
use Symfony\Component\Messenger\Envelope;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AppSeoUrlDomainListener::class)]
class AppSeoUrlDomainListenerTest extends TestCase
{
    public function testWrittenSalesChannelDomainsTriggerTheSync(): void
    {
        static::assertSame(
            ['sales_channel_domain.written' => 'syncStaticSeoUrls'],
            AppSeoUrlDomainListener::getSubscribedEvents()
        );
    }

    public function testTheStaticSeoUrlsOfAllAppsAreSynchronised(): void
    {
        $messageBus = new CollectingMessageBus();

        (new AppSeoUrlDomainListener($messageBus))->syncStaticSeoUrls();

        static::assertEquals(
            [new AppSeoUrlSyncMessage()],
            array_map(static fn (Envelope $envelope): object => $envelope->getMessage(), $messageBus->getMessages())
        );
    }
}
