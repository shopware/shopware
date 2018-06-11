<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\Country\Struct\CountrySearchResult;

class CountrySearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'country.search.result.loaded';

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

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
