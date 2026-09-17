<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlLifecycleHandler;
use Shopware\Storefront\Framework\Seo\App\Message\AppSeoUrlSyncMessage;
use Shopware\Tests\Unit\Core\Framework\App\Manifest\ManifestFixture;
use Symfony\Component\Messenger\Envelope;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AppSeoUrlLifecycleHandler::class)]
class AppSeoUrlLifecycleHandlerTest extends TestCase
{
    private const APP_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private CollectingMessageBus $messageBus;

    private AppSeoUrlLifecycleHandler $handler;

    protected function setUp(): void
    {
        $this->messageBus = new CollectingMessageBus();
        $this->handler = new AppSeoUrlLifecycleHandler($this->messageBus);
    }

    public function testActivatingAnAppRequestsAFullSync(): void
    {
        $this->handler->activate(new AppActivationContext($this->app(), Context::createDefaultContext()));

        static::assertEquals([new AppSeoUrlSyncMessage(self::APP_ID, true)], $this->dispatchedMessages());
    }

    public function testUpdatingAnActiveAppRequestsAFullSync(): void
    {
        $this->handler->update($this->persistContext(active: true));

        static::assertEquals([new AppSeoUrlSyncMessage(self::APP_ID, true)], $this->dispatchedMessages());
    }

    public function testUpdatingAnInactiveAppRequestsNothing(): void
    {
        $this->handler->update($this->persistContext(active: false));

        static::assertSame([], $this->dispatchedMessages());
    }

    /**
     * @return list<object>
     */
    private function dispatchedMessages(): array
    {
        return array_map(
            static fn (Envelope $envelope): object => $envelope->getMessage(),
            array_values($this->messageBus->getMessages())
        );
    }

    private function persistContext(bool $active): AppPersistContext
    {
        return new AppPersistContext(
            manifest: ManifestFixture::empty()->withName('SwagSeoUrlApp'),
            app: $this->app($active),
            context: Context::createDefaultContext(),
            appFilesystem: new StaticFilesystem(),
            defaultLocale: 'en-GB',
        );
    }

    private function app(bool $active = true): AppEntity
    {
        $app = new AppEntity();
        $app->setId(self::APP_ID);
        $app->setActive($active);

        return $app;
    }
}
