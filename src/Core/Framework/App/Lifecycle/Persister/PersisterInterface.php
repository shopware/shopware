<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
interface PersisterInterface
{
    public function persist(AppLifecycleContext $context): void;

    public function activate(AppEntity $app, Context $context): void;

    public function deactivate(AppEntity $app, Context $context): void;
}
