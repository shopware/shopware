<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

interface CustomerGroupAware extends FlowEventAware
{
    public function getCustomerGroupId(): string;
}
