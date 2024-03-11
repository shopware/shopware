<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\Consent\ConsentState;
use Shopware\Core\System\UsageData\Services\EntityDispatchService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('data-services')]
class UsageDataApprovalSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityDispatchService $entityDispatchService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SystemConfigChangedEvent::class => 'onDataUsageApprovalChange',
        ];
    }

    public function onDataUsageApprovalChange(SystemConfigChangedEvent $event): void
    {
        if ($event->getKey() !== ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE) {
            return;
        }

        if ($event->getValue() !== ConsentState::ACCEPTED->value) {
            return;
        }

        $this->entityDispatchService->dispatchCollectEntityDataMessage();
    }
}
