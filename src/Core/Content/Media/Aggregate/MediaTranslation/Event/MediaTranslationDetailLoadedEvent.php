<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaTranslation\Event;

use Shopware\Core\Content\Media\Aggregate\MediaTranslation\Collection\MediaTranslationDetailCollection;
use Shopware\Core\Content\Media\Event\MediaBasicLoadedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Language\Event\LanguageBasicLoadedEvent;

class MediaTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'media_translation.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var MediaTranslationDetailCollection
     */
    protected $mediaTranslations;

    public function __construct(MediaTranslationDetailCollection $mediaTranslations, Context $context)
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

    public function getMediaTranslations(): MediaTranslationDetailCollection
    {
        return $this->mediaTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->mediaTranslations->getMedia()->count() > 0) {
            $events[] = new MediaBasicLoadedEvent($this->mediaTranslations->getMedia(), $this->context);
        }
        if ($this->mediaTranslations->getLanguages()->count() > 0) {
            $events[] = new LanguageBasicLoadedEvent($this->mediaTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
