<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryStateTranslation\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\Country\Aggregate\CountryStateTranslation\Struct\CountryStateTranslationSearchResult;

class CountryStateTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'country_state_translation.search.result.loaded';

    /**
     * @var \Shopware\Core\System\Country\Aggregate\CountryStateTranslation\Struct\CountryStateTranslationSearchResult
     */
    protected $result;

    public function __construct(CountryStateTranslationSearchResult $result)
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
