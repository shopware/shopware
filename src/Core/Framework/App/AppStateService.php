<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Exception\AppNotFoundException;
use Shopware\Core\Framework\App\Payment\PaymentMethodStateService;
use Shopware\Core\Framework\App\Template\TemplateStateService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
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

    /**
     * @var TemplateStateService
     */
    private $templateStateService;

    /**
     * @var PaymentMethodStateService
     */
    private $paymentMethodStateService;

    public function __construct(
        EntityRepositoryInterface $appRepo,
        EventDispatcherInterface $eventDispatcher,
        ActiveAppsLoader $activeAppsLoader,
        TemplateStateService $templateStateService,
        PaymentMethodStateService $paymentMethodStateService
    ) {
        $this->appRepo = $appRepo;
        $this->eventDispatcher = $eventDispatcher;
        $this->activeAppsLoader = $activeAppsLoader;
        $this->templateStateService = $templateStateService;
        $this->paymentMethodStateService = $paymentMethodStateService;
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
        $this->paymentMethodStateService->activatePaymentMethods($appId, $context);
        $this->activeAppsLoader->resetActiveApps();
        // manually set active flag to true, so we don't need to re-fetch the app from DB
        $app->setActive(true);

        $this->eventDispatcher->dispatch(new AppActivatedEvent($app, $context));
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

        $this->activeAppsLoader->resetActiveApps();
        // throw event before deactivating app in db as theme configs from the app need to be removed beforehand
        $this->eventDispatcher->dispatch(new AppDeactivatedEvent($app, $context));

        $this->appRepo->update([['id' => $appId, 'active' => false]], $context);
        $this->templateStateService->deactivateAppTemplates($appId, $context);
        $this->paymentMethodStateService->deactivatePaymentMethods($appId, $context);
    }
}
