<?php declare(strict_types=1);

namespace Shopware\Api\Country\Event\CountryAreaTranslation;

use Shopware\Api\Country\Struct\CountryAreaTranslationSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class CountryAreaTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'country_area_translation.search.result.loaded';

    /**
     * @var CountryAreaTranslationSearchResult
     */
    protected $result;

    public function __construct(CountryAreaTranslationSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
