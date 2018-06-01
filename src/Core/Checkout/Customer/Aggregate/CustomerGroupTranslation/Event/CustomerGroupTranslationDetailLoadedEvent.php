<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Aggregate\CustomerGroupTranslation\Event;

use Shopware\Framework\Context;
use Shopware\Application\Language\Event\LanguageBasicLoadedEvent;
use Shopware\Checkout\Customer\Aggregate\CustomerGroup\Event\CustomerGroupBasicLoadedEvent;
use Shopware\Checkout\Customer\Aggregate\CustomerGroupTranslation\Collection\CustomerGroupTranslationDetailCollection;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CustomerGroupTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'customer_group_translation.detail.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var \Shopware\Checkout\Customer\Aggregate\CustomerGroupTranslation\Collection\CustomerGroupTranslationDetailCollection
     */
    protected $customerGroupTranslations;

    public function __construct(CustomerGroupTranslationDetailCollection $customerGroupTranslations, Context $context)
    {
        $this->context = $context;
        $this->customerGroupTranslations = $customerGroupTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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
            $events[] = new LanguageBasicLoadedEvent($this->customerGroupTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
