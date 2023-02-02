<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

class MediaThumbnailDeletedEvent extends Event
{
    public const EVENT_NAME = 'media_thumbnail.after_delete';

    /**
     * @var MediaThumbnailCollection
     */
    private $thumbnails;

    /**
     * @var Context
     */
    private $context;

    public function __construct(MediaThumbnailCollection $thumbnails, Context $context)
    {
        $this->thumbnails = $thumbnails;
        $this->context = $context;
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
