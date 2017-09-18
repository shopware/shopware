<?php declare(strict_types=1);

namespace Shopware\Customer\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Struct\CustomerDetailCollection;
use Shopware\CustomerAddress\Event\CustomerAddressBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Shop\Event\ShopBasicLoadedEvent;

class CustomerDetailLoadedEvent extends NestedEvent
{
    const NAME = 'customer.detail.loaded';

    /**
     * @var CustomerDetailCollection
     */
    protected $customers;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(CustomerDetailCollection $customers, TranslationContext $context)
    {
        $this->customers = $customers;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getCustomers(): CustomerDetailCollection
    {
        return $this->customers;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new CustomerBasicLoadedEvent($this->customers, $this->context),
            new CustomerAddressBasicLoadedEvent($this->customers->getAddresss(), $this->context),
            new ShopBasicLoadedEvent($this->customers->getShops(), $this->context),
        ]);
    }
}
