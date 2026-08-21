<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class ThumbnailGeneratedEvent extends Event
{
    final public const EVENT_NAME = 'media_thumbnail.generated';

    /**
     * @param string $path filesystem path of the generated thumbnail on $filesystem
     * @param string $mimeType MIME type of the generated thumbnail (thumbnails keep the source format)
     * @param FilesystemOperator $filesystem filesystem the thumbnail was written to, so subscribers can post-process it
     */
    public function __construct(
        private readonly string $mediaId,
        private readonly string $thumbnailId,
        private readonly string $path,
        private readonly string $mimeType,
        private readonly FilesystemOperator $filesystem,
        private readonly Context $context,
    ) {
    }

    public function getMediaId(): string
    {
        return $this->mediaId;
    }

    public function getThumbnailId(): string
    {
        return $this->thumbnailId;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getFilesystem(): FilesystemOperator
    {
        return $this->filesystem;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
