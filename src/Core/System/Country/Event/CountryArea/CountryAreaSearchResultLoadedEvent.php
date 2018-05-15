<?php declare(strict_types=1);

namespace Shopware\System\Country\Event\CountryArea;

use Shopware\System\Country\Struct\CountryAreaSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
