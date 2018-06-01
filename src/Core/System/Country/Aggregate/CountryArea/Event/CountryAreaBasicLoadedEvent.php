<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryArea\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Country\Aggregate\CountryArea\Collection\CountryAreaBasicCollection;

class CountryAreaBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'country_area.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var CountryAreaBasicCollection
     */
    protected $countryAreas;

    public function __construct(CountryAreaBasicCollection $countryAreas, Context $context)
    {
        $this->context = $context;
        $this->countryAreas = $countryAreas;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCountryAreas(): CountryAreaBasicCollection
    {
        return $this->countryAreas;
    }
}
