<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

/**
 * @package business-ops
 */
interface CustomerGroupAware extends FlowEventAware
{
    public const CUSTOMER_GROUP_ID = 'customerGroupId';

    public const CUSTOMER_GROUP = 'customerGroup';

    public function getCustomerGroupId(): string;
}
