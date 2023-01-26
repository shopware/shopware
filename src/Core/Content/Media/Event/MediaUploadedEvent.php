<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use Shopware\Core\Content\Flow\Dispatching\Aware\MediaUploadedAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('content')]
class MediaUploadedEvent extends Event implements MediaUploadedAware
{
    public const EVENT_NAME = 'media.uploaded';

    private string $mediaId;

    private Context $context;

    public function __construct(string $mediaId, Context $context)
    {
        $this->mediaId = $mediaId;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('mediaId', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getMediaId(): string
    {
        return $this->mediaId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
