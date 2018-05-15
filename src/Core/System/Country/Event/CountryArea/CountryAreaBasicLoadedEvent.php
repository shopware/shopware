<?php declare(strict_types=1);

namespace Shopware\System\Country\Event\CountryArea;

use Shopware\System\Country\Collection\CountryAreaBasicCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class CountryAreaBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'country_area.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var CountryAreaBasicCollection
     */
    protected $countryAreas;

    public function __construct(CountryAreaBasicCollection $countryAreas, ApplicationContext $context)
    {
        $this->context = $context;
        $this->countryAreas = $countryAreas;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getCountryAreas(): CountryAreaBasicCollection
    {
        return $this->countryAreas;
    }
}
