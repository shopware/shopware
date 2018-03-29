<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Event\CustomerGroupDiscount;

use Shopware\Api\Customer\Collection\CustomerGroupDiscountBasicCollection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class CustomerGroupDiscountBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'customer_group_discount.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var CustomerGroupDiscountBasicCollection
     */
    protected $customerGroupDiscounts;

    public function __construct(CustomerGroupDiscountBasicCollection $customerGroupDiscounts, ApplicationContext $context)
    {
        $this->context = $context;
        $this->customerGroupDiscounts = $customerGroupDiscounts;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getCustomerGroupDiscounts(): CustomerGroupDiscountBasicCollection
    {
        return $this->customerGroupDiscounts;
    }
}
