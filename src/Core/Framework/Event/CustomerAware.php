<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

interface CustomerAware extends FlowEventAware
{
    public const CUSTOMER_ID = 'customerId';

    public const CUSTOMER = 'customer';

    public function getCustomerId(): string;
}
