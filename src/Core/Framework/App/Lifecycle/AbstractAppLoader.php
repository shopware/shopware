<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Cms\CmsExtensions;
use Shopware\Core\Framework\App\Manifest\Manifest;

/**
 * @internal
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

    /**
     * @internal (flag:FEATURE_NEXT_14408)
     */
    abstract public function getCmsExtensions(AppEntity $app): ?CmsExtensions;
}
