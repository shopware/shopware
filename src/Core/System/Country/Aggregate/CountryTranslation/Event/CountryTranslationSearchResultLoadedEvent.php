<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryTranslation\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Country\Aggregate\CountryTranslation\Struct\CountryTranslationSearchResult;

class CountryTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'country_translation.search.result.loaded';

    /**
     * @var \Shopware\System\Country\Aggregate\CountryTranslation\Struct\CountryTranslationSearchResult
     */
    protected $result;

    public function __construct(CountryTranslationSearchResult $result)
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
