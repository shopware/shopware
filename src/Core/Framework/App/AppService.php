<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleIterator;
use Shopware\Core\Framework\App\Lifecycle\RefreshableAppDryRun;
use Shopware\Core\Framework\Context;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class AppService
{
    /**
     * @var AppLifecycleIterator
     */
    private $appLifecycleIterator;

    /**
     * @var AppLifecycle
     */
    private $appLifecycle;

    public function __construct(
        AppLifecycleIterator $appLifecycleIterator,
        AppLifecycle $appLifecycle
    ) {
        $this->appLifecycleIterator = $appLifecycleIterator;
        $this->appLifecycle = $appLifecycle;
    }

    public function doRefreshApps(bool $activateInstalled, Context $context): array
    {
        return $this->appLifecycleIterator->iterateOverApps($this->appLifecycle, $activateInstalled, $context);
    }

    public function getRefreshableAppInfo(Context $context): RefreshableAppDryRun
    {
        $appInfo = new RefreshableAppDryRun();

        $this->appLifecycleIterator->iterateOverApps($appInfo, false, $context);

        return $appInfo;
    }
}
