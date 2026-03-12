<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Provider;

use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class ProductExportTemplateFileLoader
{
    public function load(string $template): string
    {
        $path = __DIR__ . '/Templates/' . ltrim($template, '/');

        if (!is_file($path)) {
            throw new \RuntimeException(\sprintf('Product export template "%s" could not be found.', $template));
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException(\sprintf('Product export template "%s" could not be loaded.', $template));
        }

        return $contents;
    }
}
