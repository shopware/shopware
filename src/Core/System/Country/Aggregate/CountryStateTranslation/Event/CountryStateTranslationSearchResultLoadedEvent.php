<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryStateTranslation\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Country\Aggregate\CountryStateTranslation\Struct\CountryStateTranslationSearchResult;

class CountryStateTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'country_state_translation.search.result.loaded';

    /**
     * @var \Shopware\System\Country\Aggregate\CountryStateTranslation\Struct\CountryStateTranslationSearchResult
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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
