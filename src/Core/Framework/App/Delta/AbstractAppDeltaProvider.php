<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Delta;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
abstract class AbstractAppDeltaProvider
{
    abstract public function getDeltaName(): string;

    abstract public function getReport(Manifest $manifest, AppEntity $app): array;

    abstract public function hasDelta(Manifest $manifest, AppEntity $app): bool;
}
