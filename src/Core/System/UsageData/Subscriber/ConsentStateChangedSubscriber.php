<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\Definition\BackendData;
use Shopware\Core\System\Consent\Event\ConsentAcceptedEvent;
use Shopware\Core\System\Consent\Event\ConsentRevokedEvent;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\Services\EntityDispatchService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('data-services')]
class ConsentStateChangedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ConsentService $consentService,
        private readonly EntityDispatchService $entityDispatchService,
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

        $this->consentService->acceptConsent();
        $this->entityDispatchService->dispatchCollectEntityDataMessage();
    }

    public function handleConsentRevokedEvent(ConsentRevokedEvent $event): void
    {
        if ($event->consentName !== BackendData::NAME) {
            return;
        }

        $this->consentService->revokeConsent();
    }
}
