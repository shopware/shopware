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

    public static function getSubscribedEvents(): array
    {
        return [
            AppDeletedEvent::class => 'saveSecretFromDeletedApp',
            AppInstalledEvent::class => 'removeDeletedAppSecret',
            ShopIdChangedEvent::class => 'purgeOldSecretsAfterShopIdChange',
            ShopIdDeletedEvent::class => 'purgeOldSecretsAfterShopIdDeletion',
        ];
    }

    public function saveSecretFromDeletedApp(AppDeletedEvent $event): void
    {
        $criteria = new Criteria([$event->getAppId()]);
        $app = $this->appRepository->search($criteria, $event->getContext())->getEntities()->first();

        if (!$secret = $app?->getAppSecret()) {
            return;
        }

        $this->deletedAppsGateway->insertSecretForDeletedApp($app->getName(), $secret);
    }

    public function removeDeletedAppSecret(AppInstalledEvent $event): void
    {
        $this->deletedAppsGateway->deleteSecretForApp($event->getApp()->getName());
    }

    public function purgeOldSecretsAfterShopIdChange(ShopIdChangedEvent $event): void
    {
        // A permanent move refreshes the fingerprints but intentionally keeps the same shop identity. Retain
        // deleted-app secrets: an interrupted reinstall still needs both the secret and this lifecycle marker.
        if ($event->oldShopId?->id === $event->newShopId->id) {
            return;
        }

        $this->deletedAppsGateway->purgeOldSecrets();
    }

    public function purgeOldSecretsAfterShopIdDeletion(ShopIdDeletedEvent $event): void
    {
        $this->deletedAppsGateway->purgeOldSecrets();
    }
}
