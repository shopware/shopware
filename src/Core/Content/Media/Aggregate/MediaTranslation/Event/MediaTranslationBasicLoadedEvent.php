<?php declare(strict_types=1);

namespace Shopware\Content\Media\Aggregate\MediaTranslation\Event;

use Shopware\Framework\Context;
use Shopware\Content\Media\Aggregate\MediaTranslation\Collection\MediaTranslationBasicCollection;
use Shopware\Framework\Event\NestedEvent;

class MediaTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'media_translation.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var MediaTranslationBasicCollection
     */
    protected $mediaTranslations;

    public function __construct(MediaTranslationBasicCollection $mediaTranslations, Context $context)
    {
        $this->context = $context;
        $this->mediaTranslations = $mediaTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getMediaTranslations(): MediaTranslationBasicCollection
    {
        return $this->mediaTranslations;
    }
}
