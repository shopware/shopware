<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Source;

use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 *
 * @phpstan-import-type App from \Shopware\Core\Framework\App\ActiveAppsLoader
 */
#[Package('core')]
class NoDatabaseSourceResolver
{
    /**
     * @var array<string, App>
     */
    private array $activeApps = [];

    public function __construct(ActiveAppsLoader $activeAppsLoader)
    {
        $activeApps = $activeAppsLoader->getActiveApps();
        $this->activeApps = array_combine(
            array_map(fn (array $app) => $app['name'], $activeApps),
            $activeApps
        );
    }

    public function filesystem(string $appName): Filesystem
    {
        if (!isset($this->activeApps[$appName])) {
            throw AppException::notFoundByField($appName, 'name');
        }

        return new Filesystem($this->activeApps[$appName]['path']);
    }
}
