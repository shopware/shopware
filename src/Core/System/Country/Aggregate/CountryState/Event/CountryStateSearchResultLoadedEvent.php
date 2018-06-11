<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryState\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\Country\Aggregate\CountryState\Struct\CountryStateSearchResult;

class CountryStateSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'country_state.search.result.loaded';

    /**
     * @var \Shopware\Core\System\Country\Aggregate\CountryState\Struct\CountryStateSearchResult
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

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
