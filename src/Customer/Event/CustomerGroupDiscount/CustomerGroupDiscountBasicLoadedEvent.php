<?php declare(strict_types=1);

namespace Shopware\Customer\Event\CustomerGroupDiscount;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Collection\CustomerGroupDiscountBasicCollection;
use Shopware\Framework\Event\NestedEvent;

class CustomerGroupDiscountBasicLoadedEvent extends NestedEvent
{
    const NAME = 'customer_group_discount.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CustomerGroupDiscountBasicCollection
     */
    protected $customerGroupDiscounts;

    public function __construct(CustomerGroupDiscountBasicCollection $customerGroupDiscounts, TranslationContext $context)
    {
        $this->context = $context;
        $this->customerGroupDiscounts = $customerGroupDiscounts;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getCustomerGroupDiscounts(): CustomerGroupDiscountBasicCollection
    {
        return $this->customerGroupDiscounts;
    }
}
