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

    public function __construct(
        EntityRepositoryInterface $appRepo,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->appRepo = $appRepo;
        $this->eventDispatcher = $eventDispatcher;
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

        $this->eventDispatcher->dispatch(new AppDeactivatedEvent($appId, $context));
    }
}
