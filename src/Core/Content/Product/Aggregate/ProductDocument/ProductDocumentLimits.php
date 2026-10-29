<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductDocument;

use Shopware\Core\Content\Product\Events\ProductDocumentAllowedExtensionsEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\MemorySizeCalculator;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The limits for product documents, configured in `shopware.product_documents`.
 * The file types work like the ones of the media library: extensions can change them at runtime.
 *
 * @internal
 */
#[Package('inventory')]
readonly class ProductDocumentLimits
{
    /**
     * @param list<string> $allowedExtensions
     * @param int $maxFileSize in bytes
     */
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private array $allowedExtensions,
        public int $maxPerLanguage,
        public int $maxFileSize,
    ) {
    }

    /**
     * @return list<string> lower case, without a leading dot
     */
    public function getAllowedExtensions(Context $context): array
    {
        $event = new ProductDocumentAllowedExtensionsEvent($this->allowedExtensions, $context);
        $this->eventDispatcher->dispatch($event);

        $extensions = array_map(
            static fn (string $extension): string => mb_strtolower(ltrim(trim($extension), '.')),
            $event->getAllowedExtensions(),
        );

        return array_values(array_unique(array_filter($extensions, static fn (string $extension): bool => $extension !== '')));
    }

    /**
     * @return array<string, list<string>>
     */
    public function getMimeTypesByExtension(Context $context): array
    {
        $mimeTypes = MimeTypes::getDefault();

        $mimeTypesByExtension = [];
        foreach ($this->getAllowedExtensions($context) as $extension) {
            $mimeTypesByExtension[$extension] = array_values(array_unique($mimeTypes->getMimeTypes($extension)));
        }

        return $mimeTypesByExtension;
    }

    /**
     * The largest file that can be uploaded: PHP's `upload_max_filesize` and `post_max_size` can lower the configured size.
     */
    public function getMaxUploadFileSize(): int
    {
        return MemorySizeCalculator::getMaxUploadSize($this->maxFileSize);
    }
}
