<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Event\Hooks\AppActivatedHook;
use Shopware\Core\Framework\App\Event\Hooks\AppDeactivatedHook;
use Shopware\Core\Framework\App\Exception\AppNotFoundException;
use Shopware\Core\Framework\App\Lifecycle\Persister\ScriptPersister;
use Shopware\Core\Framework\App\Payment\PaymentMethodStateService;
use Shopware\Core\Framework\App\Template\TemplateStateService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class AppStateService
{
    private EntityRepositoryInterface $appRepo;

    private EventDispatcherInterface $eventDispatcher;

    private ActiveAppsLoader $activeAppsLoader;

    private TemplateStateService $templateStateService;

    private ScriptPersister $scriptPersister;

    private PaymentMethodStateService $paymentMethodStateService;

    private ScriptExecutor $scriptExecutor;

    public function __construct(
        EntityRepositoryInterface $appRepo,
        EventDispatcherInterface $eventDispatcher,
        ActiveAppsLoader $activeAppsLoader,
        TemplateStateService $templateStateService,
        ScriptPersister $scriptPersister,
        PaymentMethodStateService $paymentMethodStateService,
        ScriptExecutor $scriptExecutor
    ) {
        $this->appRepo = $appRepo;
        $this->eventDispatcher = $eventDispatcher;
        $this->activeAppsLoader = $activeAppsLoader;
        $this->templateStateService = $templateStateService;
        $this->paymentMethodStateService = $paymentMethodStateService;
        $this->scriptPersister = $scriptPersister;
        $this->scriptExecutor = $scriptExecutor;
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
        $this->templateStateService->activateAppTemplates($appId, $context);
        $this->scriptPersister->activateAppScripts($appId, $context);
        $this->paymentMethodStateService->activatePaymentMethods($appId, $context);
        $this->activeAppsLoader->reset();
        // manually set active flag to true, so we don't need to re-fetch the app from DB
        $app->setActive(true);

        $event = new AppActivatedEvent($app, $context);
        $this->eventDispatcher->dispatch($event);
        $this->scriptExecutor->execute(new AppActivatedHook($event));
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

        $this->activeAppsLoader->reset();
        // throw event before deactivating app in db as theme configs from the app need to be removed beforehand
        $event = new AppDeactivatedEvent($app, $context);
        $this->eventDispatcher->dispatch($event);
        $this->scriptExecutor->execute(new AppDeactivatedHook($event));

        $this->appRepo->update([['id' => $appId, 'active' => false]], $context);
        $this->templateStateService->deactivateAppTemplates($appId, $context);
        $this->scriptPersister->deactivateAppScripts($appId, $context);
        $this->paymentMethodStateService->deactivatePaymentMethods($appId, $context);
    }
}
