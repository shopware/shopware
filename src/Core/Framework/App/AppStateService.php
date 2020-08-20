<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Exception\AppNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AppStateService
{
    /**
     * @var EntityRepositoryInterface
     */
    private $appRepo;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ActiveAppsLoader
     */
    private $activeAppsLoader;

    public function __construct(
        EntityRepositoryInterface $appRepo,
        EventDispatcherInterface $eventDispatcher,
        ActiveAppsLoader $activeAppsLoader
    ) {
        $this->appRepo = $appRepo;
        $this->eventDispatcher = $eventDispatcher;
        $this->activeAppsLoader = $activeAppsLoader;
    }

    public function activateApp(string $appId, Context $context): void
    {
        /** @var AppEntity|null $app */
        $app = $this->appRepo->search(new Criteria([$appId]), $context)->first();

        if (!$app) {
            throw new AppNotFoundException($appId);
        }
        if ($app->isActive()) {
            return;
        }

        $this->appRepo->update([['id' => $appId, 'active' => true]], $context);
        $this->activeAppsLoader->resetActiveApps();

        $this->eventDispatcher->dispatch(new AppActivatedEvent($appId, $context));
    }

    public function deactivateApp(string $appId, Context $context): void
    {
        /** @var AppEntity|null $app */
        $app = $this->appRepo->search(new Criteria([$appId]), $context)->first();

        if (!$app) {
            throw new AppNotFoundException($appId);
        }
        if (!$app->isActive()) {
            return;
        }

        $this->appRepo->update([['id' => $appId, 'active' => false]], $context);
        $this->activeAppsLoader->resetActiveApps();

        $this->eventDispatcher->dispatch(new AppDeactivatedEvent($appId, $context));
    }
}
