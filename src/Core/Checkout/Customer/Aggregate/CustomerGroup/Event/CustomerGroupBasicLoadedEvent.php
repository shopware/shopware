<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\Event;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\Collection\CustomerGroupBasicCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class CustomerGroupBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'customer_group.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var \Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\Collection\CustomerGroupBasicCollection
     */
    protected $customerGroups;

    public function __construct(CustomerGroupBasicCollection $customerGroups, Context $context)
    {
        $this->context = $context;
        $this->customerGroups = $customerGroups;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCustomerGroups(): CustomerGroupBasicCollection
    {
        return $this->customerGroups;
    }
}
