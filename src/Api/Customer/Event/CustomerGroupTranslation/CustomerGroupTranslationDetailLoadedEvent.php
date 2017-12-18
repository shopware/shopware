<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Event\CustomerGroupTranslation;

use Shopware\Api\Customer\Collection\CustomerGroupTranslationDetailCollection;
use Shopware\Api\Customer\Event\CustomerGroup\CustomerGroupBasicLoadedEvent;
use Shopware\Api\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CustomerGroupTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'customer_group_translation.detail.loaded';

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
