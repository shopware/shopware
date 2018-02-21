<?php declare(strict_types=1);

namespace Shopware\Api\Country\Event\CountryAreaTranslation;

use Shopware\Api\Country\Collection\CountryAreaTranslationBasicCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;

class CountryAreaTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'country_area_translation.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var CountryAreaTranslationBasicCollection
     */
    protected $countryAreaTranslations;

    public function __construct(CountryAreaTranslationBasicCollection $countryAreaTranslations, ShopContext $context)
    {
        $this->context = $context;
        $this->countryAreaTranslations = $countryAreaTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getCountryAreaTranslations(): CountryAreaTranslationBasicCollection
    {
        return $this->countryAreaTranslations;
    }
}
