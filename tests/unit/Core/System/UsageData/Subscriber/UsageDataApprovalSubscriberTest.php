<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\Consent\ConsentState;
use Shopware\Core\System\UsageData\Services\EntityDispatchService;
use Shopware\Core\System\UsageData\Subscriber\UsageDataApprovalSubscriber;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(UsageDataApprovalSubscriber::class)]
class UsageDataApprovalSubscriberTest extends TestCase
{
    public function testItSubscribesToSystemConfigChanged(): void
    {
        static::assertArrayHasKey(SystemConfigChangedEvent::class, UsageDataApprovalSubscriber::getSubscribedEvents());
    }

    public function testItStartsDataSyncWhenApprovalWasGiven(): void
    {
        $entityDispatchService = $this->createMock(EntityDispatchService::class);
        $entityDispatchService->expects(static::once())
            ->method('dispatchCollectEntityDataMessage');

        (new UsageDataApprovalSubscriber($entityDispatchService))->onDataUsageApprovalChange(
            new SystemConfigChangedEvent(
                ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE,
                ConsentState::ACCEPTED->value,
                null
            )
        );
    }

    public function testItDoesNotDispatchCollectionMessageWhenConfigKeyIsNotCorrect(): void
    {
        $entityDispatchService = $this->createMock(EntityDispatchService::class);
        $entityDispatchService->expects(static::never())
            ->method('dispatchCollectEntityDataMessage');

        (new UsageDataApprovalSubscriber($entityDispatchService))->onDataUsageApprovalChange(
            new SystemConfigChangedEvent(
                'some.configuration.thing',
                true,
                null
            )
        );
    }

    public function testItDoesNotDispatchCollectionMessageWhenApprovalWasNotGiven(): void
    {
        $entityDispatchService = $this->createMock(EntityDispatchService::class);
        $entityDispatchService->expects(static::never())
            ->method('dispatchCollectEntityDataMessage');

        (new UsageDataApprovalSubscriber($entityDispatchService))->onDataUsageApprovalChange(
            new SystemConfigChangedEvent(
                ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE,
                ConsentState::REVOKED->value,
                null
            )
        );
    }
}
