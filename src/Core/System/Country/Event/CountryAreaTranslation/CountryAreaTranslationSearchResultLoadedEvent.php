<?php declare(strict_types=1);

namespace Shopware\System\Country\Event\CountryAreaTranslation;

use Shopware\System\Country\Struct\CountryAreaTranslationSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class CountryAreaTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'country_area_translation.search.result.loaded';

    /**
     * @var CountryAreaTranslationSearchResult
     */
    protected $result;

    public function __construct(CountryAreaTranslationSearchResult $result)
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
