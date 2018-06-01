<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryStateTranslation\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Country\Aggregate\CountryStateTranslation\Collection\CountryStateTranslationBasicCollection;

class CountryStateTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'country_state_translation.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var \Shopware\System\Country\Aggregate\CountryStateTranslation\Collection\CountryStateTranslationBasicCollection
     */
    protected $countryStateTranslations;

    public function __construct(CountryStateTranslationBasicCollection $countryStateTranslations, Context $context)
    {
        $this->context = $context;
        $this->countryStateTranslations = $countryStateTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCountryStateTranslations(): CountryStateTranslationBasicCollection
    {
        return $this->countryStateTranslations;
    }
}
