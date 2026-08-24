<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Theme\Message\CompileThemeFailedSubscriber;
use Shopware\Storefront\Theme\Message\CompileThemeMessage;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CompileThemeFailedSubscriber::class)]
class CompileThemeFailedSubscriberTest extends TestCase
{
    public function testDoesNothingWhileTheMessageWillStillRetry(): void
    {
        $themeId = Uuid::randomHex();
        $configService = $this->configWithPendingTheme($themeId);

        // a non-terminal failure (Messenger will retry) must not notify or clear the marker
        $notificationService = $this->createMock(NotificationService::class);
        $notificationService->expects($this->never())->method('createNotification');

        $subscriber = new CompileThemeFailedSubscriber($notificationService, $configService);
        $subscriber->onWorkerMessageFailed($this->failedEvent($this->assignMessage($themeId), willRetry: true));

        static::assertSame($themeId, $configService->getString(ThemeService::CONFIG_KEY_PENDING_THEME, TestDefaults::SALES_CHANNEL));
    }

    public function testIgnoresUnrelatedMessages(): void
    {
        $notificationService = $this->createMock(NotificationService::class);
        $notificationService->expects($this->never())->method('createNotification');

        $subscriber = new CompileThemeFailedSubscriber($notificationService, new StaticSystemConfigService());
        $subscriber->onWorkerMessageFailed($this->failedEvent(new \stdClass()));
    }

    public function testIgnoresCompileMessagesThatDoNotAssign(): void
    {
        $themeId = Uuid::randomHex();
        $configService = $this->configWithPendingTheme($themeId);

        $notificationService = $this->createMock(NotificationService::class);
        $notificationService->expects($this->never())->method('createNotification');

        // a compile-only message (no assignment) never had a pending marker to clean up
        $message = new CompileThemeMessage(TestDefaults::SALES_CHANNEL, $themeId, true, Context::createDefaultContext());

        $subscriber = new CompileThemeFailedSubscriber($notificationService, $configService);
        $subscriber->onWorkerMessageFailed($this->failedEvent($message));

        static::assertSame($themeId, $configService->getString(ThemeService::CONFIG_KEY_PENDING_THEME, TestDefaults::SALES_CHANNEL));
    }

    public function testClearsPendingMarkerAndNotifiesOnTerminalFailure(): void
    {
        $themeId = Uuid::randomHex();
        $configService = $this->configWithPendingTheme($themeId);
        // AdminApiSource -> USER_SCOPE, so the user is notified
        $context = Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()));

        $notificationService = $this->createMock(NotificationService::class);
        $notificationService->expects($this->once())->method('createNotification')->with(
            static::callback(static fn (array $notification): bool => $notification['status'] === 'warning'
                && $notification['message'] === 'sw-theme-manager.detail.asyncCompilation.error'),
            $context
        );

        $subscriber = new CompileThemeFailedSubscriber($notificationService, $configService);
        $subscriber->onWorkerMessageFailed($this->failedEvent($this->assignMessage($themeId, $context)));

        // the marker is cleared so the Administration stops polling for the failed switch
        static::assertSame('', $configService->getString(ThemeService::CONFIG_KEY_PENDING_THEME, TestDefaults::SALES_CHANNEL));
    }

    public function testKeepsANewerPendingRequestOnTerminalFailure(): void
    {
        $failedThemeId = Uuid::randomHex();
        $newerThemeId = Uuid::randomHex();
        // a newer switch was requested in the meantime, so the marker points at another theme
        $configService = $this->configWithPendingTheme($newerThemeId);
        $context = Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()));

        $notificationService = $this->createMock(NotificationService::class);
        $notificationService->expects($this->once())->method('createNotification');

        $subscriber = new CompileThemeFailedSubscriber($notificationService, $configService);
        $subscriber->onWorkerMessageFailed($this->failedEvent($this->assignMessage($failedThemeId, $context)));

        // the newer request's marker must be preserved
        static::assertSame($newerThemeId, $configService->getString(ThemeService::CONFIG_KEY_PENDING_THEME, TestDefaults::SALES_CHANNEL));
    }

    public function testDoesNotNotifyOutsideUserScope(): void
    {
        $themeId = Uuid::randomHex();
        $configService = $this->configWithPendingTheme($themeId);

        // system scope: still clean up the marker, but do not raise an admin notification
        $notificationService = $this->createMock(NotificationService::class);
        $notificationService->expects($this->never())->method('createNotification');

        $subscriber = new CompileThemeFailedSubscriber($notificationService, $configService);
        $subscriber->onWorkerMessageFailed($this->failedEvent($this->assignMessage($themeId, Context::createDefaultContext())));

        static::assertSame('', $configService->getString(ThemeService::CONFIG_KEY_PENDING_THEME, TestDefaults::SALES_CHANNEL));
    }

    private function configWithPendingTheme(string $themeId): StaticSystemConfigService
    {
        return new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [ThemeService::CONFIG_KEY_PENDING_THEME => $themeId],
        ]);
    }

    private function assignMessage(string $themeId, ?Context $context = null): CompileThemeMessage
    {
        return new CompileThemeMessage(
            TestDefaults::SALES_CHANNEL,
            $themeId,
            true,
            $context ?? Context::createDefaultContext(),
            true
        );
    }

    private function failedEvent(object $message, bool $willRetry = false): WorkerMessageFailedEvent
    {
        $event = new WorkerMessageFailedEvent(new Envelope($message), 'async', new \RuntimeException('compile failed'));

        if ($willRetry) {
            $event->setForRetry();
        }

        return $event;
    }
}
