<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaTranslation\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\Collection\MediaTranslationBasicCollection;
use Shopware\Core\Framework\Event\NestedEvent;

class MediaTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'media_translation.basic.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
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
