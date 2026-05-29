<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
interface BundleConfigStyleFileResolver
{
    /**
     * @return array<string>
     */
    public function resolveStyleFiles(string $technicalName, string $basePath): array;
}
