<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Provider;

use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
readonly class ProductExportTemplate
{
    public function __construct(
        public string $fileName,
        public string $encoding,
        public string $fileFormat,
        public int $interval,
        public bool $includeVariants,
        public bool $generateByCronjob,
        public string $headerTemplate,
        public string $bodyTemplate,
        public string $footerTemplate
    ) {
    }
}
