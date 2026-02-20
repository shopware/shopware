<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\Definition\BackendData;
use Shopware\Core\System\Consent\Event\ConsentAcceptedEvent;
use Shopware\Core\System\Consent\Event\ConsentRevokedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\UsageData\Services\EntityDispatchService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('data-services')]
class ConsentStateChangedSubscriber implements EventSubscriberInterface
{
    private const LEGACY_CONFIG_KEY = 'core.usageData.consentState';

    public function __construct(
        private readonly EntityDispatchService $entityDispatchService,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ConsentAcceptedEvent::class => 'handleConsentAcceptedEvent',
            ConsentRevokedEvent::class => 'handleConsentRevokedEvent',
        ];
    }

    public function handleConsentAcceptedEvent(ConsentAcceptedEvent $event): void
    {
        if ($event->consentName !== BackendData::NAME) {
            return;
        }

        $this->systemConfigService->set(self::LEGACY_CONFIG_KEY, ConsentStatus::ACCEPTED->value);

        $this->entityDispatchService->dispatchCollectEntityDataMessage();
    }

    public function handleConsentRevokedEvent(ConsentRevokedEvent $event): void
    {
        if ($event->consentName !== BackendData::NAME) {
            return;
        }

        $this->systemConfigService->set(self::LEGACY_CONFIG_KEY, ConsentStatus::REVOKED->value);
    }
}
