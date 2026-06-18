<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Shopware\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppUpdateParameters;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
abstract class AbstractAppLifecycle
{
    /**
     * Context state flag: when present on the deletion context,
     * the app-uninstall flow must not trigger theme recompilation.
     */
    public const STATE_SKIP_THEME_COMPILATION = 'skip-theme-compilation';

    abstract public function getDecorated(): AbstractAppLifecycle;

    abstract public function install(Manifest $manifest, AppInstallParameters $parameters, Context $context): void;

    /**
     * @param array{id: string, roleId: string} $app
     */
    abstract public function update(Manifest $manifest, AppUpdateParameters $parameters, array $app, Context $context): void;

    /**
     * @param array{id: string} $app
     */
    abstract public function uninstall(string $appName, array $app, Context $context, bool $keepUserData = false): void;
}
