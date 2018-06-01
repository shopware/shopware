<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryState\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Country\Aggregate\CountryState\Collection\CountryStateBasicCollection;

class CountryStateBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'country_state.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var CountryStateBasicCollection
     */
    protected $countryStates;

    public function __construct(CountryStateBasicCollection $countryStates, Context $context)
    {
        $this->context = $context;
        $this->countryStates = $countryStates;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCountryStates(): CountryStateBasicCollection
    {
        return $this->countryStates;
    }
}
