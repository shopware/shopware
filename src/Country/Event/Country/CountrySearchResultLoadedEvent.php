<?php declare(strict_types=1);

namespace Shopware\Country\Event\Country;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Country\Struct\CountrySearchResult;
use Shopware\Framework\Event\NestedEvent;

class CountrySearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'country.search.result.loaded';

    /**
     * @var CountrySearchResult
     */
    protected $result;

    public function __construct(CountrySearchResult $result)
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
