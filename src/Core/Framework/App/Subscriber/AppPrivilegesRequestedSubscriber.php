<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Subscriber;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppsUpdatedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppPrivilegesRequestedSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly NotificationService $notificationService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AppsUpdatedEvent::class => 'onAppsUpdated',
        ];
    }

    public function onAppsUpdated(AppsUpdatedEvent $event): void
    {
        if (\count($event->appIds) === 0) {
            return;
        }

        $apps = $this->appRepository->search(new Criteria($event->appIds), $event->getContext())->getEntities();

        $numAppsWithRequestedPrivileges = $apps->filter(fn (AppEntity $app) => \count($app->getRequestedPrivileges()) > 0)->count();

        if ($numAppsWithRequestedPrivileges === 0) {
            return;
        }

        $this->notificationService->createNotification(
            [
                'id' => Uuid::randomHex(),
                'status' => 'warning',
                'message' => 'notification.permissions.requested',
                'adminOnly' => true,
                'requiredPrivileges' => [],
            ],
            $event->getContext()
        );
    }
}
