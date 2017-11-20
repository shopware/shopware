<?php declare(strict_types=1);

namespace Shopware\Customer\Event\CustomerGroupTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Collection\CustomerGroupTranslationDetailCollection;
use Shopware\Customer\Event\CustomerGroup\CustomerGroupBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Shop\Event\Shop\ShopBasicLoadedEvent;

class CustomerGroupTranslationDetailLoadedEvent extends NestedEvent
{
    const NAME = 'customer_group_translation.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CustomerGroupTranslationDetailCollection
     */
    protected $customerGroupTranslations;

    public function __construct(CustomerGroupTranslationDetailCollection $customerGroupTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->customerGroupTranslations = $customerGroupTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getCustomerGroupTranslations(): CustomerGroupTranslationDetailCollection
    {
        return $this->customerGroupTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->customerGroupTranslations->getCustomerGroups()->count() > 0) {
            $events[] = new CustomerGroupBasicLoadedEvent($this->customerGroupTranslations->getCustomerGroups(), $this->context);
        }
        if ($this->customerGroupTranslations->getLanguages()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->customerGroupTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
