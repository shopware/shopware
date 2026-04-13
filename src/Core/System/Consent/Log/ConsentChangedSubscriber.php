<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Log;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\Event\ConsentAcceptedEvent;
use Shopware\Core\System\Consent\Event\ConsentRevokedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('data-services')]
class ConsentChangedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ?ConsentLogInterface $consentChangeLogger,
    ) {
    }

    public static function getSubscribedEvents()
    {
        return [
            ConsentAcceptedEvent::class => 'onConsentAccepted',
            ConsentRevokedEvent::class => 'onConsentRevoked',
        ];
    }

    public function onConsentAccepted(ConsentAcceptedEvent $event): void
    {
        $this->logConsentChange(ConsentStatus::ACCEPTED, $event->consentName, $event->identifier, $event->actor);
    }

    public function onConsentRevoked(ConsentRevokedEvent $event): void
    {
        $this->logConsentChange(ConsentStatus::REVOKED, $event->consentName, $event->identifier, $event->actor);
    }

    private function logConsentChange(ConsentStatus $consentStatus, string $consentName, string $identifier, string $actor): void
    {
        if ($this->consentChangeLogger === null) {
            return;
        }

        $this->consentChangeLogger->log(
            $consentStatus,
            $consentName,
            $identifier,
            $actor,
        );
    }
}
