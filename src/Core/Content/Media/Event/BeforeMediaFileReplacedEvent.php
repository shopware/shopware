<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched with the new file's values before an upload replaces a media's file.
 * Throw to stop the replace; the media keeps its old file.
 * Presigned uploads dispatch it in `prepare()` with the declared file and in `finalize()` with the stored one.
 * With `shopware.cdn.path_cache_buster` off, a presigned upload of the same name and extension
 * already overwrote the old file before `finalize()`.
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
final class BeforeMediaFileReplacedEvent extends Event implements ShopwareEvent
{
    /**
     * @param int|null $fileSize in bytes, null if a presigned upload didn't declare it
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
