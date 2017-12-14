<?php declare(strict_types=1);

namespace Shopware\Api\Country\Event\CountryAreaTranslation;

use Shopware\Api\Country\Collection\CountryAreaTranslationBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class CountryAreaTranslationBasicLoadedEvent extends NestedEvent
{
    const NAME = 'country_area_translation.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CountryAreaTranslationBasicCollection
     */
    protected $countryAreaTranslations;

    public function __construct(CountryAreaTranslationBasicCollection $countryAreaTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->countryAreaTranslations = $countryAreaTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getCountryAreaTranslations(): CountryAreaTranslationBasicCollection
    {
        return $this->countryAreaTranslations;
    }
}
