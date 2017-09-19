<?php

namespace Shopware\CustomerGroupDiscount\Event;

use Shopware\Framework\Write\FieldAware\FieldExtenderCollection;
use Symfony\Component\EventDispatcher\Event;

class CustomerGroupDiscountWriteExtenderEvent extends Event
{
    const NAME = 'customer_group_discount.write.extender';

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
