<?php declare(strict_types=1);

namespace Shopware\Country\Event\CountryStateTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Country\Collection\CountryStateTranslationBasicCollection;
use Shopware\Framework\Event\NestedEvent;

class CountryStateTranslationBasicLoadedEvent extends NestedEvent
{
    const NAME = 'country_state_translation.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CountryStateTranslationBasicCollection
     */
    protected $countryStateTranslations;

    public function __construct(CountryStateTranslationBasicCollection $countryStateTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->countryStateTranslations = $countryStateTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getCountryStateTranslations(): CountryStateTranslationBasicCollection
    {
        return $this->countryStateTranslations;
    }
}
