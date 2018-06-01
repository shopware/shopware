<?php declare(strict_types=1);

namespace Shopware\System\Listing\Aggregate\ListingSortingTranslation\Event;

use Shopware\Framework\Context;
use Shopware\System\Language\Event\LanguageBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\System\Listing\Aggregate\ListingSortingTranslation\Collection\ListingSortingTranslationDetailCollection;
use Shopware\System\Listing\Event\ListingSortingBasicLoadedEvent;

class ListingSortingTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'listing_sorting_translation.detail.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var \Shopware\System\Listing\Aggregate\ListingSortingTranslation\Collection\ListingSortingTranslationDetailCollection
     */
    protected $listingSortingTranslations;

    public function __construct(ListingSortingTranslationDetailCollection $listingSortingTranslations, Context $context)
    {
        $this->context = $context;
        $this->listingSortingTranslations = $listingSortingTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getListingSortingTranslations(): ListingSortingTranslationDetailCollection
    {
        return $this->listingSortingTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->listingSortingTranslations->getListingSortings()->count() > 0) {
            $events[] = new ListingSortingBasicLoadedEvent($this->listingSortingTranslations->getListingSortings(), $this->context);
        }
        if ($this->listingSortingTranslations->getLanguages()->count() > 0) {
            $events[] = new LanguageBasicLoadedEvent($this->listingSortingTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
