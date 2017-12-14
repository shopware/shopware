<?php declare(strict_types=1);

namespace Shopware\Api\Country\Event\CountryArea;

use Shopware\Api\Country\Collection\CountryAreaBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class CountryAreaBasicLoadedEvent extends NestedEvent
{
    const NAME = 'country_area.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CountryAreaBasicCollection
     */
    protected $countryAreas;

    public function __construct(CountryAreaBasicCollection $countryAreas, TranslationContext $context)
    {
        $this->context = $context;
        $this->countryAreas = $countryAreas;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getCountryAreas(): CountryAreaBasicCollection
    {
        return $this->countryAreas;
    }
}
