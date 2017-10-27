<?php declare(strict_types=1);

namespace Shopware\Order\Event;

use Shopware\Api\Write\FieldAware\FieldExtenderCollection;
use Symfony\Component\EventDispatcher\Event;

class OrderWriteExtenderEvent extends Event
{
    const NAME = 'order.write.extender';

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
