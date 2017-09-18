<?php

namespace Shopware\AreaCountryState\Event;

use Shopware\Framework\Write\FieldAware\FieldExtenderCollection;
use Symfony\Component\EventDispatcher\Event;

class AreaCountryStateWriteExtenderEvent extends Event
{
    const NAME = 'area_country_state.write.extender';

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
