<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryTranslation\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Country\Aggregate\CountryTranslation\Collection\CountryTranslationBasicCollection;

class CountryTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'country_translation.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var \Shopware\System\Country\Aggregate\CountryTranslation\Collection\CountryTranslationBasicCollection
     */
    protected $countryTranslations;

    public function __construct(CountryTranslationBasicCollection $countryTranslations, Context $context)
    {
        $this->context = $context;
        $this->countryTranslations = $countryTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCountryTranslations(): CountryTranslationBasicCollection
    {
        return $this->countryTranslations;
    }
}
