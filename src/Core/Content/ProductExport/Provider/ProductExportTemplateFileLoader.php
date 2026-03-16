<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Provider;

use Shopware\Core\Content\ProductExport\ProductExportException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 */
#[Package('discovery')]
class ProductExportTemplateFileLoader
{
    public function load(string $template): string
    {
        $path = __DIR__ . '/Templates/' . ltrim($template, '/');

        if (!is_file($path)) {
            throw ProductExportException::templateFileNotFound($template);
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw ProductExportException::templateFileNotLoadable($template);
        }

        return $contents;
    }
}
