<?php declare(strict_types=1);

namespace Shopware\Api\Listing\Event\ListingSortingTranslation;

use Shopware\Api\Listing\Collection\ListingSortingTranslationDetailCollection;
use Shopware\Api\Listing\Event\ListingSorting\ListingSortingBasicLoadedEvent;
use Shopware\Api\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ListingSortingTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'listing_sorting_translation.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ListingSortingTranslationDetailCollection
     */
    protected $listingSortingTranslations;

    public function __construct(ListingSortingTranslationDetailCollection $listingSortingTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->listingSortingTranslations = $listingSortingTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
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
            $events[] = new ShopBasicLoadedEvent($this->listingSortingTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
