<?php declare(strict_types=1);

namespace Shopware\Core\Service\Subscriber;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\Requirement\ServiceConsentRequirement;
use Shopware\Core\System\Consent\Definition\ServiceConsent;
use Shopware\Core\System\Consent\Event\ConsentAcceptedEvent;
use Shopware\Core\System\Consent\Event\ConsentRevokedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[Package('framework')]
readonly class ServiceConsentSubscriber implements EventSubscriberInterface
{
    public function __construct(private LifecycleManager $manager)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsentAcceptedEvent::class => 'syncConsentRequirement',
            ConsentRevokedEvent::class => 'syncConsentRequirement',
        ];
    }

    public function syncConsentRequirement(ConsentAcceptedEvent|ConsentRevokedEvent $event): void
    {
        if ($event->consentName !== ServiceConsent::NAME) {
            return;
        }

        $this->manager->syncRequirement(ServiceConsentRequirement::NAME, Context::createDefaultContext());
    }
}
