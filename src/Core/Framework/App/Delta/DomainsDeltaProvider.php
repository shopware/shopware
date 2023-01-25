<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Delta;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class DomainsDeltaProvider extends AbstractAppDeltaProvider
{
    final public const DELTA_NAME = 'domains';

    public function getDeltaName(): string
    {
        return self::DELTA_NAME;
    }

    public function getReport(Manifest $manifest, AppEntity $app): array
    {
        return $manifest->getAllHosts();
    }

    public function hasDelta(Manifest $manifest, AppEntity $app): bool
    {
        $hosts = $manifest->getAllHosts();

        if (\count($hosts) < 1) {
            return false;
        }

        if (!$app->getAllowedHosts()) {
            return true;
        }

        return \count(array_diff($hosts, $app->getAllowedHosts())) > 0;
    }
}
