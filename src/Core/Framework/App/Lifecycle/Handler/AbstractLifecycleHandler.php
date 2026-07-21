<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Handler;

use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppRemovalContext;
use Shopware\Core\Framework\Log\Package;

/**
 * Lifecycle handlers take part in the app lifecycle without going through the event system, so they
 * also run in flows that deliberately stay invisible to the app servers.
 *
 * Override the hooks relevant for your domain; each is a no-op by default.
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
abstract class AbstractLifecycleHandler
{
    public function install(AppPersistContext $context): void
    {
    }

    public function update(AppPersistContext $context): void
    {
    }

    public function activate(AppActivationContext $context): void
    {
    }

    public function deactivate(AppActivationContext $context): void
    {
    }

    /**
     * Called when an app is uninstalled, before any of its data is deleted.
     */
    public function uninstall(AppRemovalContext $context): void
    {
    }

    /**
     * Called when an app is removed locally without notifying its app server, before any of its data is deleted.
     */
    public function delete(AppRemovalContext $context): void
    {
    }
}
