<?php declare(strict_types=1);

namespace Shopware\Core\Service\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Event\NewServicesInstalledEvent;
use Shopware\Core\Service\Notification;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class NewServicesInstalledSubscriber implements EventSubscriberInterface
{
    public function __construct(private Notification $notification)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            NewServicesInstalledEvent::class => 'sendNewServicesInstalledNotification',
        ];
    }

    public function sendNewServicesInstalledNotification(NewServicesInstalledEvent $event): void
    {
        $this->notification->newServicesInstalled();
    }
}
