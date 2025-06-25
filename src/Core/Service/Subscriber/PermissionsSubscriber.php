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
            PermissionsGrantedEvent::class => 'enableServices',
            PermissionsRevokedEvent::class => 'disableServices',
        ];
    }

    public function enableServices(PermissionsGrantedEvent $event): void
    {
        $this->manager->enable($event->getContext());
    }

    public function disableServices(PermissionsRevokedEvent $event): void
    {
        $this->manager->disable($event->getContext());
    }
}
