<?php declare(strict_types=1);

namespace Shopware\Content\Media\Aggregate\MediaTranslation\Event;

use Shopware\Application\Language\Event\Language\LanguageBasicLoadedEvent;
use Shopware\Content\Media\Aggregate\MediaTranslation\Collection\MediaTranslationDetailCollection;
use Shopware\Content\Media\Event\MediaBasicLoadedEvent;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class MediaTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'media_translation.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var MediaTranslationDetailCollection
     */
    protected $mediaTranslations;

    public function __construct(MediaTranslationDetailCollection $mediaTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->mediaTranslations = $mediaTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
