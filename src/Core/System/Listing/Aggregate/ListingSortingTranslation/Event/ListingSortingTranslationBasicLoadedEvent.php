<?php declare(strict_types=1);

namespace Shopware\System\Listing\Aggregate\ListingSortingTranslation\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Listing\Aggregate\ListingSortingTranslation\Collection\ListingSortingTranslationBasicCollection;

class ListingSortingTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'listing_sorting_translation.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var \Shopware\System\Listing\Aggregate\ListingSortingTranslation\Collection\ListingSortingTranslationBasicCollection
     */
    protected $listingSortingTranslations;

    public function __construct(ListingSortingTranslationBasicCollection $listingSortingTranslations, Context $context)
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

    public function getListingSortingTranslations(): ListingSortingTranslationBasicCollection
    {
        return $this->listingSortingTranslations;
    }
}
