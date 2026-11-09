<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductDocument;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\MemorySizeCalculator;
use Symfony\Component\Mime\MimeTypes;

/**
 * The `shopware.product_documents` settings: allowed file types, documents per product and file size.
 *
 * @internal
 */
#[Package('inventory')]
readonly class ProductDocumentConfig
{
    /**
     * @var list<string> lower case, without a leading dot
     */
    public array $allowedExtensions;

    /**
     * @var array<string, list<string>>
     */
    public array $mimeTypesByExtension;

    /**
     * @param list<string> $allowedExtensions
     * @param int $maxFileSize in bytes
     */
    public function __construct(
        array $allowedExtensions,
        public int $maxPerProduct,
        public int $maxFileSize,
    ) {
        $extensions = array_map(
            static fn (string $extension): string => mb_strtolower(ltrim(trim($extension), '.')),
            $allowedExtensions,
        );
        $this->allowedExtensions = array_values(array_unique(array_filter($extensions, static fn (string $extension): bool => $extension !== '')));

        $mimeTypes = MimeTypes::getDefault();
        $mimeTypesByExtension = [];
        foreach ($this->allowedExtensions as $extension) {
            $mimeTypesByExtension[$extension] = array_values(array_unique($mimeTypes->getMimeTypes($extension)));
        }
        $this->mimeTypesByExtension = $mimeTypesByExtension;
    }

    /**
     * The largest file an upload through PHP can carry: the configured size, lowered by PHP's `post_max_size`.
     * The Administration sends the raw file, so `upload_max_filesize` doesn't apply.
     */
    public function getMaxUploadFileSize(): int
    {
        $postMaxSize = MemorySizeCalculator::convertToBytes((string) ini_get('post_max_size'));

        return $postMaxSize > 0 ? min($this->maxFileSize, $postMaxSize) : $this->maxFileSize;
    }
}
