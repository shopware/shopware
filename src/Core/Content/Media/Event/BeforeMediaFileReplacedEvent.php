<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched before an upload replaces the file of a media.
 * A listener can throw an exception to stop the replace, and the media keeps its old file.
 * Presigned uploads dispatch it in `prepare()` with the declared file, and in `finalize()` with the stored one.
 * With `shopware.cdn.path_cache_buster` off, a presigned upload of the same file name and extension
 * has already overwritten the old file when `finalize()` stops it.
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
final class BeforeMediaFileReplacedEvent extends Event implements ShopwareEvent
{
    /**
     * @param string $fileExtension the extension of the new file
     * @param string $mimeType the MIME type of the new file
     * @param int|null $fileSize the size of the new file in bytes, null when a presigned upload didn't declare it
     */
    public function __construct(
        public readonly string $mediaId,
        public readonly string $fileExtension,
        public readonly string $mimeType,
        public readonly ?int $fileSize,
        private readonly Context $context,
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
