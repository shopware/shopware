<?php declare(strict_types=1);

namespace Shopware\Core\Service\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Event\ServiceInstalledEvent;
use Shopware\Core\Service\Event\ServiceUpdatedEvent;
use Shopware\Core\Service\LifecycleManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class ServiceLifecycleSubscriber implements EventSubscriberInterface
{
    public function __construct(private LifecycleManager $lifecycleManager)
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ServiceInstalledEvent::class => 'syncState',
            ServiceUpdatedEvent::class => 'syncState',
        ];
    }

    public function syncState(ServiceInstalledEvent|ServiceUpdatedEvent $event): void
    {
        $this->lifecycleManager->syncState($event->service, $event->getContext());
    }
}
