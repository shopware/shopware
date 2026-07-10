<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('discovery')]
class ThumbnailGeneratedEvent extends Event
{
    final public const EVENT_NAME = 'media_thumbnail.generated';

    public function __construct(
        private readonly string $mediaId,
        private readonly string $thumbnailId,
        private readonly string $path,
        private readonly string $mimeType,
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

    public function getContext(): Context
    {
        return $this->context;
    }
}
