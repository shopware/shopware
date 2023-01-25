<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event\Hooks;

use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryWriterFacadeHookFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * @internal
 */
#[Package('core')]
abstract class AppLifecycleHook extends Hook
{
    public static function getServiceIds(): array
    {
        return [
            RepositoryFacadeHookFactory::class,
            SystemConfigFacadeHookFactory::class,
            RepositoryWriterFacadeHookFactory::class,
        ];
    }
}
