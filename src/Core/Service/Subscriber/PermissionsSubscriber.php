<?php declare(strict_types=1);

namespace Shopware\Core\Service\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Event\PermissionsGrantedEvent;
use Shopware\Core\Service\Event\PermissionsRevokedEvent;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\Requirement\ServiceConsentRequirement;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class PermissionsSubscriber implements EventSubscriberInterface
{
    public function __construct(private LifecycleManager $manager)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PermissionsGrantedEvent::class => 'syncConsentRequirement',
            PermissionsRevokedEvent::class => 'syncConsentRequirement',
        ];
    }

    public function syncConsentRequirement(PermissionsGrantedEvent|PermissionsRevokedEvent $event): void
    {
        $this->manager->syncRequirement(
            ServiceConsentRequirement::NAME,
            $event->getContext()
        );
    }
}
