<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryAreaTranslation\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Country\Aggregate\CountryAreaTranslation\Collection\CountryAreaTranslationBasicCollection;

class CountryAreaTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'country_area_translation.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var \Shopware\System\Country\Aggregate\CountryAreaTranslation\Collection\CountryAreaTranslationBasicCollection
     */
    protected $countryAreaTranslations;

    public function __construct(CountryAreaTranslationBasicCollection $countryAreaTranslations, Context $context)
    {
        $this->context = $context;
        $this->countryAreaTranslations = $countryAreaTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCountryAreaTranslations(): CountryAreaTranslationBasicCollection
    {
        return $this->countryAreaTranslations;
    }
}
