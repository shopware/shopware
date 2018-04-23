<?php declare(strict_types=1);

namespace Shopware\Api\Listing\Event\ListingSortingTranslation;

use Shopware\Api\Language\Event\Language\LanguageBasicLoadedEvent;
use Shopware\Api\Listing\Collection\ListingSortingTranslationDetailCollection;
use Shopware\Api\Listing\Event\ListingSorting\ListingSortingBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ListingSortingTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'listing_sorting_translation.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ListingSortingTranslationDetailCollection
     */
    protected $listingSortingTranslations;

    public function __construct(ListingSortingTranslationDetailCollection $listingSortingTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->listingSortingTranslations = $listingSortingTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
