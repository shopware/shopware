<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryArea\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Country\Aggregate\CountryArea\Struct\CountryAreaSearchResult;

class CountryAreaSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'country_area.search.result.loaded';

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

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
