<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class NullBundleConfigStyleFileResolver implements BundleConfigStyleFileResolver
{
    public function resolveStyleFiles(string $technicalName, string $basePath): array
    {
        return [];
    }
}
