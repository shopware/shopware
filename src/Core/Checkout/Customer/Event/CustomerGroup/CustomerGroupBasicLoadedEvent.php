<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Event\CustomerGroup;

use Shopware\Checkout\Customer\Collection\CustomerGroupBasicCollection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class CustomerGroupBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'customer_group.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var CustomerGroupBasicCollection
     */
    protected $customerGroups;

    public function __construct(CustomerGroupBasicCollection $customerGroups, ApplicationContext $context)
    {
        $this->context = $context;
        $this->customerGroups = $customerGroups;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getCustomerGroups(): CustomerGroupBasicCollection
    {
        return $this->customerGroups;
    }
}
