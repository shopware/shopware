<?php declare(strict_types=1);

namespace Shopware\System\Country\Event\CountryStateTranslation;

use Shopware\System\Country\Struct\CountryStateTranslationSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
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
