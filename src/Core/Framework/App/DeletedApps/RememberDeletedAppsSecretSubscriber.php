<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\DeletedApps;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\ShopId\ShopIdChangedEvent;
use Shopware\Core\Framework\App\ShopId\ShopIdDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class RememberDeletedAppsSecretSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private EntityRepository $appRepository,
        private DeletedAppsGateway $deletedAppsGateway,
    ) {
    }

    public static function getSubscribedEvents()
    {
        return [
            AppDeletedEvent::class => 'saveSecretFromDeletedApp',
            AppInstalledEvent::class => 'removeDeletedAppSecret',
            ShopIdChangedEvent::class => 'purgeOldSecrets',
            ShopIdDeletedEvent::class => 'purgeOldSecrets',
        ];
    }

    public function saveSecretFromDeletedApp(AppDeletedEvent $event): void
    {
        $criteria = new Criteria([$event->getAppId()]);
        $app = $this->appRepository->search($criteria, $event->getContext())->first();

        if (!$secret = $app?->getAppSecret()) {
            return;
        }

        $this->deletedAppsGateway->insertSecretForDeletedApp($app->getName(), $secret);
    }

    public function removeDeletedAppSecret(AppInstalledEvent $event): void
    {
        $this->deletedAppsGateway->deleteSecretForApp($event->getApp()->getName());
    }

    /**
     * When the shopId changes, all current apps are re-registered
     * stored old secrets should be dismissed, as they are only valid when you re-install the app on the same shopId
     */
    public function purgeOldSecrets(): void
    {
        $this->deletedAppsGateway->purgeOldSecrets();
    }
}
