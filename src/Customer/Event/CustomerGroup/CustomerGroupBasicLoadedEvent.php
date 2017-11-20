<?php declare(strict_types=1);

namespace Shopware\Customer\Event\CustomerGroup;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Collection\CustomerGroupBasicCollection;
use Shopware\Framework\Event\NestedEvent;

class CustomerGroupBasicLoadedEvent extends NestedEvent
{
    const NAME = 'customer_group.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CustomerGroupBasicCollection
     */
    protected $customerGroups;

    public function __construct(CustomerGroupBasicCollection $customerGroups, TranslationContext $context)
    {
        $this->context = $context;
        $this->customerGroups = $customerGroups;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getCustomerGroups(): CustomerGroupBasicCollection
    {
        return $this->customerGroups;
    }
}
