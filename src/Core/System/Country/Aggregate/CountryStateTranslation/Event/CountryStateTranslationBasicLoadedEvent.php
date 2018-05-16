<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryStateTranslation\Event;

use Shopware\System\Country\Aggregate\CountryStateTranslation\Collection\CountryStateTranslationBasicCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class CountryStateTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'country_state_translation.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var \Shopware\System\Country\Aggregate\CountryStateTranslation\Collection\CountryStateTranslationBasicCollection
     */
    protected $countryStateTranslations;

    public function __construct(CountryStateTranslationBasicCollection $countryStateTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->countryStateTranslations = $countryStateTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getCountryStateTranslations(): CountryStateTranslationBasicCollection
    {
        return $this->countryStateTranslations;
    }
}
