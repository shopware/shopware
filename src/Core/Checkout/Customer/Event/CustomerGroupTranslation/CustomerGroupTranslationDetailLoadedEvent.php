<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Event\CustomerGroupTranslation;

use Shopware\Checkout\Customer\Collection\CustomerGroupTranslationDetailCollection;
use Shopware\Checkout\Customer\Event\CustomerGroup\CustomerGroupBasicLoadedEvent;
use Shopware\Application\Language\Event\Language\LanguageBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CustomerGroupTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'customer_group_translation.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var CustomerGroupTranslationDetailCollection
     */
    protected $customerGroupTranslations;

    public function __construct(CustomerGroupTranslationDetailCollection $customerGroupTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->customerGroupTranslations = $customerGroupTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
