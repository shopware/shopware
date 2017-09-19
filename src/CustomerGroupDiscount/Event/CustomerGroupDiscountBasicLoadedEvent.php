<?php declare(strict_types=1);

namespace Shopware\CustomerGroupDiscount\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroupDiscount\Struct\CustomerGroupDiscountBasicCollection;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CustomerGroupDiscountBasicLoadedEvent extends NestedEvent
{
    const NAME = 'customerGroupDiscount.basic.loaded';

    /**
     * @var CustomerGroupDiscountBasicCollection
     */
    protected $customerGroupDiscounts;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(CustomerGroupDiscountBasicCollection $customerGroupDiscounts, TranslationContext $context)
    {
        $this->customerGroupDiscounts = $customerGroupDiscounts;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getCustomerGroupDiscounts(): CustomerGroupDiscountBasicCollection
    {
        return $this->customerGroupDiscounts;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
        ]);
    }
}
