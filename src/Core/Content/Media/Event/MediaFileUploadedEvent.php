<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

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
     * @var string
     */
    private $mediaId;

    /**
     * @var string
     */
    private $mimeType;

    /**
     * @var string
     */
    private $fileExtension;

    public function __construct(string $mediaId, string $mimeType, string $fileExtension, Context $context)
    {
        $this->context = $context;
        $this->mediaId = $mediaId;
        $this->mimeType = $mimeType;
        $this->fileExtension = $fileExtension;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getMediaId(): string
    {
        return $this->mediaId;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getFileExtension(): string
    {
        return $this->fileExtension;
    }
}
