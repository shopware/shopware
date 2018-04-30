<?php declare(strict_types=1);

namespace Shopware\Api\Country\Event\CountryState;

use Shopware\Api\Country\Struct\CountryStateSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class CountryStateSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'country_state.search.result.loaded';

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
