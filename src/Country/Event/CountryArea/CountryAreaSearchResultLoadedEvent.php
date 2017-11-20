<?php declare(strict_types=1);

namespace Shopware\Country\Event\CountryArea;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Country\Struct\CountryAreaSearchResult;
use Shopware\Framework\Event\NestedEvent;

class CountryAreaSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'country_area.search.result.loaded';

    /**
     * @var CountryAreaSearchResult
     */
    protected $result;

    public function __construct(CountryAreaSearchResult $result)
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
