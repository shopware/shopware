<?php

namespace Shopware\Shop\Event;

use Shopware\Framework\Write\FieldAware\FieldExtenderCollection;
use Symfony\Component\EventDispatcher\Event;

class ShopWriteExtenderEvent extends Event
{
    const NAME = 'shop.write.extender';

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
