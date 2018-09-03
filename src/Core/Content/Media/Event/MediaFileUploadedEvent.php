<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\Event;

class MediaFileUploadedEvent extends Event
{
    public const EVENT_NAME = 'media.upload.finished';

    /**
     * @var Context
     */
    private $context;

    /**
     * @var MediaStruct
     */
    private $media;

    public function __construct(MediaStruct $media, Context $context)
    {
        $this->context = $context;
        $this->media = $media;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getMedia(): MediaStruct
    {
        return $this->media;
    }
}
