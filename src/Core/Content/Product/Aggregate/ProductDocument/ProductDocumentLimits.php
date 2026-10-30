<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductDocument;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\MemorySizeCalculator;
use Symfony\Component\Mime\MimeTypes;

/**
 * The limits for product documents, configured in `shopware.product_documents`.
 *
 * @internal
 */
#[Package('inventory')]
readonly class ProductDocumentLimits
{
    /**
     * @var list<string> lower case, without a leading dot
     */
    public array $allowedExtensions;

    /**
     * @param list<string> $allowedExtensions
     * @param int $maxFileSize in bytes
     */
    public function __construct(
        array $allowedExtensions,
        public int $maxPerLanguage,
        public int $maxFileSize,
    ) {
        $extensions = array_map(
            static fn (string $extension): string => mb_strtolower(ltrim(trim($extension), '.')),
            $allowedExtensions,
        );

        $this->allowedExtensions = array_values(array_unique(array_filter($extensions, static fn (string $extension): bool => $extension !== '')));
    }

    /**
     * @return array<string, list<string>>
     */
    public function getMimeTypesByExtension(): array
    {
        $mimeTypes = MimeTypes::getDefault();

        $mimeTypesByExtension = [];
        foreach ($this->allowedExtensions as $extension) {
            $mimeTypesByExtension[$extension] = array_values(array_unique($mimeTypes->getMimeTypes($extension)));
        }

        return $mimeTypesByExtension;
    }

    /**
     * The largest file the Administration can upload. It sends the file as the request body, so PHP's `post_max_size`
     * can lower the configured size, and `upload_max_filesize` doesn't apply. A presigned upload goes straight to the
     * storage, past PHP.
     */
    public function getMaxUploadFileSize(bool $presignedUploadSupported): int
    {
        $postMaxSize = MemorySizeCalculator::convertToBytes((string) ini_get('post_max_size'));
        if ($presignedUploadSupported || $postMaxSize <= 0) {
            return $this->maxFileSize;
        }

        return min($this->maxFileSize, $postMaxSize);
    }
}
