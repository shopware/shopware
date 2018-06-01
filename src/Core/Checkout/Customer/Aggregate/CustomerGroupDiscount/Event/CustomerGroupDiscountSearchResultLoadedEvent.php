<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Aggregate\CustomerGroupDiscount\Event;

use Shopware\Framework\Context;
use Shopware\Checkout\Customer\Aggregate\CustomerGroupDiscount\Struct\CustomerGroupDiscountSearchResult;
use Shopware\Framework\Event\NestedEvent;

class CustomerGroupDiscountSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'customer_group_discount.search.result.loaded';

    /**
     * @var \Shopware\Checkout\Customer\Aggregate\CustomerGroupDiscount\Struct\CustomerGroupDiscountSearchResult
     */
    protected $result;

    public function __construct(CustomerGroupDiscountSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
