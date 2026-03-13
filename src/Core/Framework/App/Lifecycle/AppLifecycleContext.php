<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @codeCoverageIgnore
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
final readonly class AppLifecycleContext
{
    public function __construct(
        public Manifest $manifest,
        public AppEntity $app,
        public Context $context,
        /**
         * A filesystem scoped to the root of the extracted app
         */
        public Filesystem $appFilesystem,
        public string $defaultLocale,
        public bool $isInstall,
    ) {
    }

    public function hasAppSecret(): bool
    {
        return (bool) $this->app->getAppSecret();
    }
}
