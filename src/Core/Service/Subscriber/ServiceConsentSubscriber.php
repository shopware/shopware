<?php declare(strict_types=1);

namespace Shopware\Core\Service\Subscriber;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Consent\ServiceConsent;
use Shopware\Core\Service\ServiceLifecycle;
use Shopware\Core\System\Consent\Event\ConsentAcceptedEvent;
use Shopware\Core\System\Consent\Event\ConsentRevokedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class ServiceConsentSubscriber implements EventSubscriberInterface
{
    public function __construct(private ServiceLifecycle $serviceLifecycle)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsentAcceptedEvent::class => 'reevaluateServices',
            ConsentRevokedEvent::class => 'reevaluateServices',
        ];
    }

    public function reevaluateServices(ConsentAcceptedEvent|ConsentRevokedEvent $event): void
    {
        if ($event->consentName !== ServiceConsent::NAME) {
            return;
        }

        $this->serviceLifecycle->reevaluateInstalled(Context::createDefaultContext());
    }
}
