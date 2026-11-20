<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Document;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
final class ProductDocumentArchive
{
    public function __construct(
        public readonly string $path,
        public readonly string $fileName,
        public readonly int $size,
    ) {
    }
}
