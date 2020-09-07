<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleIterator;
use Shopware\Core\Framework\App\Lifecycle\RefreshableAppDryRun;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;

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

    /**
     * @return Manifest[]
     */
    public function refreshApps(bool $activateInstalled, Context $context): array
    {
        return $this->appLifecycleIterator->iterate($this->appLifecycle, $activateInstalled, $context);
    }

    public function getRefreshableAppInfo(Context $context): RefreshableAppDryRun
    {
        $appInfo = new RefreshableAppDryRun();

        $this->appLifecycleIterator->iterate($appInfo, false, $context);

        return $appInfo;
    }
}
