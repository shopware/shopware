<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Delta;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Manifest\Manifest;

/**
 * @internal only for use by the app-system
 */
abstract class AbstractAppDeltaProvider
{
    abstract public function getDeltaName(): string;

    abstract public function getDelta(Manifest $manifest, AppEntity $app): array;

    abstract public function hasDelta(Manifest $manifest, AppEntity $app): bool;
}
