<?php declare(strict_types=1);

namespace Shopware\Customer\Event\CustomerGroupDiscount;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Struct\CustomerGroupDiscountSearchResult;
use Shopware\Framework\Event\NestedEvent;

class CustomerGroupDiscountSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'customer_group_discount.search.result.loaded';

    /**
     * @var CustomerGroupDiscountSearchResult
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

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
