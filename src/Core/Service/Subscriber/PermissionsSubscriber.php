<?php declare(strict_types=1);

namespace Shopware\Core\Service\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Event\PermissionsGrantedEvent;
use Shopware\Core\Service\Event\PermissionsRevokedEvent;
use Shopware\Core\Service\Manager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class PermissionsSubscriber implements EventSubscriberInterface
{
    public function __construct(private Manager $manager)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PermissionsGrantedEvent::class => 'startServices',
            PermissionsRevokedEvent::class => 'stopServices',
        ];
    }

    public function startServices(PermissionsGrantedEvent $event): void
    {
        $this->manager->startServices($event->getContext());
    }

    public function stopServices(PermissionsRevokedEvent $event): void
    {
        $this->manager->stopServices($event->getContext());
    }
}
