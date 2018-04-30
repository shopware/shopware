<?php declare(strict_types=1);

namespace Shopware\Api\Country\Event\CountryAreaTranslation;

use Shopware\Api\Country\Collection\CountryAreaTranslationBasicCollection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class CountryAreaTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'country_area_translation.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var CountryAreaTranslationBasicCollection
     */
    protected $countryAreaTranslations;

    public function __construct(CountryAreaTranslationBasicCollection $countryAreaTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->countryAreaTranslations = $countryAreaTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getCountryAreaTranslations(): CountryAreaTranslationBasicCollection
    {
        return $this->countryAreaTranslations;
    }
}
