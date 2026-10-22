<?php declare(strict_types=1);

namespace Shopware\Core\Service\Subscriber;

use Shopware\Core\Framework\Api\Event\AdminInfoConfigEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\LifecycleManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class AdminInfoConfigSubscriber implements EventSubscriberInterface
{
    public function __construct(private LifecycleManager $lifecycleManager)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AdminInfoConfigEvent::class => 'addServicesConfig',
        ];
    }

    public function addServicesConfig(AdminInfoConfigEvent $event): void
    {
        $event->addConfig('services', $this->lifecycleManager->availability());
    }
}
