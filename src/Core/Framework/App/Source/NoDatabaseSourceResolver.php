<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Source;

use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class NoDatabaseSourceResolver
{
    public function __construct(private readonly ActiveAppsLoader $activeAppsLoader)
    {
    }

    public function filesystem(string $appName): Filesystem
    {
        foreach ($this->activeAppsLoader->getActiveApps() as $activeApp) {
            if ($activeApp['name'] === $appName) {
                return new Filesystem($activeApp['path']);
            }
        }

        throw AppException::notFoundByField($appName, 'name');
    }
}
