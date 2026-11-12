<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched before an upload replaces the file of a media, while the old file still exists.
 * A listener can throw an exception to stop the replace, and the media keeps its old file.
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
final class BeforeMediaFileReplacedEvent extends Event implements ShopwareEvent
{
    /**
     * @param string $fileExtension the extension of the new file
     * @param string $mimeType the MIME type of the new file
     * @param int $fileSize the size of the new file, in bytes
     */
    public function __construct(
        public readonly string $mediaId,
        public readonly string $fileExtension,
        public readonly string $mimeType,
        public readonly int $fileSize,
        private readonly Context $context,
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
