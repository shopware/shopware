<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Event\Hooks\AppActivatedHook;
use Shopware\Core\Framework\App\Event\Hooks\AppDeactivatedHook;
use Shopware\Core\Framework\App\Lifecycle\Persister\PersisterInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppStateService
{
    /**
     * @param EntityRepository<AppCollection> $appRepo
     * @param iterable<PersisterInterface> $persisters
     */
    public function __construct(
        private readonly EntityRepository $appRepo,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ActiveAppsLoader $activeAppsLoader,
        private readonly ScriptExecutor $scriptExecutor,
        private readonly iterable $persisters
    ) {
    }

    public function activateApp(string $appId, Context $context): void
    {
        $app = $this->appRepo->search(new Criteria([$appId]), $context)->getEntities()->first();

        if ($app === null) {
            throw AppException::notFound($appId);
        }
        if ($app->isActive()) {
            return;
        }

        $this->appRepo->update([['id' => $appId, 'active' => true]], $context);
        // manually set active flag to true, so we don't need to re-fetch the app from DB
        $app->setActive(true);
        foreach ($this->persisters as $persister) {
            $persister->activate($app, $context);
        }

        $this->activeAppsLoader->reset();

        $event = new AppActivatedEvent($app, $context);
        $this->eventDispatcher->dispatch($event);
        $this->scriptExecutor->execute(new AppActivatedHook($event));
    }

    public function deactivateApp(string $appId, Context $context, bool $deactivateForDeletion = false): void
    {
        $app = $this->appRepo->search(new Criteria([$appId]), $context)->getEntities()->first();

        if ($app === null) {
            throw AppException::notFound($appId);
        }
        if (!$app->isActive()) {
            return;
        }
        if (!$deactivateForDeletion && !$app->getAllowDisable()) {
            throw AppException::restrictDeletePreventsDeactivation($app->getName());
        }

        // throw event before deactivating app in db as theme configs from the app need to be removed beforehand
        $event = new AppDeactivatedEvent($app, $context);
        $this->eventDispatcher->dispatch($event);
        $this->scriptExecutor->execute(new AppDeactivatedHook($event));

        $this->appRepo->update([['id' => $appId, 'active' => false]], $context);
        $app->setActive(false);
        foreach ($this->persisters as $persister) {
            $persister->deactivate($app, $context);
        }

        // reset only after new state is in the DB
        $this->activeAppsLoader->reset();
    }
}
