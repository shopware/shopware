<?php declare(strict_types=1);

namespace Shopware\System\Country\Event\CountryTranslation;

use Shopware\System\Country\Struct\CountryTranslationSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class CountryTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'country_translation.search.result.loaded';

    /**
     * @var CountryTranslationSearchResult
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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
