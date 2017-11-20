<?php declare(strict_types=1);

namespace Shopware\Country\Event\CountryState;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Country\Struct\CountryStateSearchResult;
use Shopware\Framework\Event\NestedEvent;

class CountryStateSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'country_state.search.result.loaded';

    /**
     * @var CountryStateSearchResult
     */
    protected $result;

    public function __construct(CountryStateSearchResult $result)
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
