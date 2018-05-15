<?php declare(strict_types=1);

namespace Shopware\System\Country\Event\Country;

use Shopware\System\Country\Collection\CountryBasicCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class CountryBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'country.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var CountryBasicCollection
     */
    protected $countries;

    public function __construct(CountryBasicCollection $countries, ApplicationContext $context)
    {
        $this->context = $context;
        $this->countries = $countries;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getCountries(): CountryBasicCollection
    {
        return $this->countries;
    }
}
