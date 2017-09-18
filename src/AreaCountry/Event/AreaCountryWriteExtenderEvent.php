<?php

namespace Shopware\AreaCountry\Event;

use Shopware\Framework\Write\FieldAware\FieldExtenderCollection;
use Symfony\Component\EventDispatcher\Event;

class AreaCountryWriteExtenderEvent extends Event
{
    const NAME = 'area_country.write.extender';

    /**
     * @var FieldExtenderCollection
     */
    protected $extenderCollection;

    public function __construct(FieldExtenderCollection $extenderCollection)
    {
        $this->extenderCollection = $extenderCollection;
    }

    public function getExtenderCollection(): FieldExtenderCollection
    {
        return $this->extenderCollection;
    }
}
