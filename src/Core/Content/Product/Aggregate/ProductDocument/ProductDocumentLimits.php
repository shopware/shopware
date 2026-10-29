<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductDocument;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\MemorySizeCalculator;

/**
 * The limits for product documents, configured in `shopware.product_documents`.
 *
 * @internal
 */
#[Package('inventory')]
class ProductDocumentLimits
{
    /**
     * @var list<string> lower case, without a leading dot
     */
    public readonly array $allowedExtensions;

    /**
     * @param list<string> $allowedExtensions
     * @param int $maxFileSize in bytes
     */
    public function __construct(
        array $allowedExtensions,
        public readonly int $maxPerLanguage,
        public readonly int $maxFileSize,
    ) {
        $this->allowedExtensions = array_values(array_unique(array_map(
            static fn (string $extension): string => mb_strtolower(ltrim(trim($extension), '.')),
            $allowedExtensions,
        )));
    }

    public function isAllowedExtension(?string $extension): bool
    {
        return $extension !== null && \in_array(mb_strtolower($extension), $this->allowedExtensions, true);
    }

    /**
     * The largest file that can be uploaded: PHP's `upload_max_filesize` and `post_max_size` can lower the configured size.
     */
    public function getMaxUploadFileSize(): int
    {
        return MemorySizeCalculator::getMaxUploadSize($this->maxFileSize);
    }
}
