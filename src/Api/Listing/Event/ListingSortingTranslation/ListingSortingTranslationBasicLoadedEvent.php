<?php declare(strict_types=1);

namespace Shopware\Api\Listing\Event\ListingSortingTranslation;

use Shopware\Api\Listing\Collection\ListingSortingTranslationBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class ListingSortingTranslationBasicLoadedEvent extends NestedEvent
{
    const NAME = 'listing_sorting_translation.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ListingSortingTranslationBasicCollection
     */
    protected $listingSortingTranslations;

    public function __construct(ListingSortingTranslationBasicCollection $listingSortingTranslations, TranslationContext $context)
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

    public function getListingSortingTranslations(): ListingSortingTranslationBasicCollection
    {
        return $this->listingSortingTranslations;
    }
}
