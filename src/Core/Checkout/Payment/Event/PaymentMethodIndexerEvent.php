<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class PaymentMethodIndexerEvent extends NestedEvent
{
    private array $ids;

    private Context $context;

    private array $skip;

    public function __construct(array $ids, Context $context, array $skip = [])
    {
        $this->context = $context;
        $this->ids = $ids;
        $this->skip = $skip;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    public function getSkip(): array
    {
        return $this->skip;
    }
}
