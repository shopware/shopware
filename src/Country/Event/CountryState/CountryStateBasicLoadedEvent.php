<?php declare(strict_types=1);

namespace Shopware\Country\Event\CountryState;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Country\Collection\CountryStateBasicCollection;
use Shopware\Framework\Event\NestedEvent;

class CountryStateBasicLoadedEvent extends NestedEvent
{
    const NAME = 'country_state.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CountryStateBasicCollection
     */
    protected $countryStates;

    public function __construct(CountryStateBasicCollection $countryStates, TranslationContext $context)
    {
        $this->context = $context;
        $this->countryStates = $countryStates;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getCountryStates(): CountryStateBasicCollection
    {
        return $this->countryStates;
    }
}
