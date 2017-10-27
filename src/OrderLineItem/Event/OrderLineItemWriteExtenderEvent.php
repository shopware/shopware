<?php declare(strict_types=1);

namespace Shopware\OrderLineItem\Event;

use Shopware\Api\Write\FieldAware\FieldExtenderCollection;
use Symfony\Component\EventDispatcher\Event;

class OrderLineItemWriteExtenderEvent extends Event
{
    const NAME = 'order_line_item.write.extender';

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
