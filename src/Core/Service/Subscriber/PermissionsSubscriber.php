<?php declare(strict_types=1);

namespace Shopware\Core\Service\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\FirstRunWizardService;
use Shopware\Core\Service\AllServiceInstaller;
use Shopware\Core\Service\Event\PermissionsGrantedEvent;
use Shopware\Core\Service\Event\PermissionsRevokedEvent;
use Shopware\Core\Service\ServiceLifecycle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class PermissionsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ServiceLifecycle $serviceLifecycle,
        private AllServiceInstaller $serviceInstaller,
        private FirstRunWizardService $firstRunWizardService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PermissionsGrantedEvent::class => 'onGranted',
            PermissionsRevokedEvent::class => 'reevaluateServices',
        ];
    }

    /**
     * Apps with a service counterpart are only converted once the service's privilege requirements are met,
     * so a grant schedules an install run instead of leaving the conversion to the next daily one.
     * While the first run wizard is pending, the install task runs once it is finished.
     */
    public function onGranted(PermissionsGrantedEvent $event): void
    {
        $this->reevaluateServices($event);

        if (!$this->firstRunWizardService->frwShouldRun()) {
            $this->serviceInstaller->scheduleInstall();
        }
    }

    public function reevaluateServices(PermissionsGrantedEvent|PermissionsRevokedEvent $event): void
    {
        $this->serviceLifecycle->reevaluateInstalled($event->getContext());
    }
}
