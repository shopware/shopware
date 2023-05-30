<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('content')]
class MediaThumbnailDeletedEvent extends Event
{
    final public const EVENT_NAME = 'media_thumbnail.after_delete';

    public function __construct(
        private readonly MediaThumbnailCollection $thumbnails,
        private readonly Context $context
    ) {
    }

    public function getThumbnails(): MediaThumbnailCollection
    {
        return $this->thumbnails;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
