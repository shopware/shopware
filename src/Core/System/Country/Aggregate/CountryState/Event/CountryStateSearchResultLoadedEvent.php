<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryState\Event;

use Shopware\System\Country\Aggregate\CountryState\Struct\CountryStateSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class CountryStateSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'country_state.search.result.loaded';

    /**
     * @var \Shopware\System\Country\Aggregate\CountryState\Struct\CountryStateSearchResult
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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
