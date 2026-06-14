<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Handler;

use Shopware\Core\Framework\App\ContentSystem\ElementTypeStateService;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Persister\ContentSystemElementTypePersister;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ContentSystemElementTypeLifecycleHandler extends AbstractLifecycleHandler
{
    public function __construct(
        private readonly ContentSystemElementTypePersister $persister,
        private readonly ElementTypeStateService $stateService,
    ) {
    }

    public function install(AppPersistContext $context): void
    {
        $this->persister->persist($context);
    }

    public function update(AppPersistContext $context): void
    {
        $this->persister->persist($context);
    }

    public function activate(AppActivationContext $context): void
    {
        $this->stateService->activateElementTypes($context->app->getId(), $context->context);
    }

    public function deactivate(AppActivationContext $context): void
    {
        $this->stateService->deactivateElementTypes($context->app->getId(), $context->context);
    }
}
