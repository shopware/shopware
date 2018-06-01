<?php declare(strict_types=1);

namespace Shopware\System\Country\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Country\Collection\CountryBasicCollection;

class CountryBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'country.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var CountryBasicCollection
     */
    protected $countries;

    public function __construct(CountryBasicCollection $countries, Context $context)
    {
        $this->context = $context;
        $this->countries = $countries;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCountries(): CountryBasicCollection
    {
        return $this->countries;
    }
}
