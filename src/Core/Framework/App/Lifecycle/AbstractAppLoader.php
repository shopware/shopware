<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Manifest\Manifest;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
abstract class AbstractAppLoader
{
    abstract public function getDecorated(): AbstractAppLoader;

    /**
     * @return Manifest[]
     */
    abstract public function load(): array;

    abstract public function getIcon(Manifest $app): ?string;

    abstract public function getConfiguration(AppEntity $app): ?array;

    abstract public function deleteApp(string $technicalName): void;
}
