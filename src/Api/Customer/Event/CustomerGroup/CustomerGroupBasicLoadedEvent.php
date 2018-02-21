<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Event\CustomerGroup;

use Shopware\Api\Customer\Collection\CustomerGroupBasicCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;

class CustomerGroupBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'customer_group.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var CustomerGroupBasicCollection
     */
    protected $customerGroups;

    public function __construct(CustomerGroupBasicCollection $customerGroups, ShopContext $context)
    {
        $this->context = $context;
        $this->customerGroups = $customerGroups;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getCustomerGroups(): CustomerGroupBasicCollection
    {
        return $this->customerGroups;
    }
}
