<?php declare(strict_types=1);

namespace Shopware\Api\Country\Event\CountryTranslation;

use Shopware\Api\Country\Struct\CountryTranslationSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class CountryTranslationSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'country_translation.search.result.loaded';

    /**
     * @var CountryTranslationSearchResult
     */
    protected $result;

    public function __construct(CountryTranslationSearchResult $result)
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
