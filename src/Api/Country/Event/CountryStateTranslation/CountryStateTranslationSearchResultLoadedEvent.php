<?php declare(strict_types=1);

namespace Shopware\Api\Country\Event\CountryStateTranslation;

use Shopware\Api\Country\Struct\CountryStateTranslationSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class CountryStateTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'country_state_translation.search.result.loaded';

    /**
     * @var CountryStateTranslationSearchResult
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
