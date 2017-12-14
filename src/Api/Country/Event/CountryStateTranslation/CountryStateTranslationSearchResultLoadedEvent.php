<?php declare(strict_types=1);

namespace Shopware\Api\Country\Event\CountryStateTranslation;

use Shopware\Api\Country\Struct\CountryStateTranslationSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class CountryStateTranslationSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'country_state_translation.search.result.loaded';

    /**
     * @var CountryStateTranslationSearchResult
     */
    protected $result;

    public function __construct(CountryStateTranslationSearchResult $result)
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
