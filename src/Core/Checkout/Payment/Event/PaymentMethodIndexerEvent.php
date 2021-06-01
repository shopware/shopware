<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class PaymentMethodIndexerEvent extends NestedEvent
{
    private array $ids;

    private Context $context;

    public function __construct(array $ids, Context $context)
    {
        $this->context = $context;
        $this->ids = $ids;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getIds(): array
    {
        return $this->ids;
    }
}
