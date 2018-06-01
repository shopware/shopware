<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\Collection\CustomerGroupTranslationBasicCollection;
use Shopware\Core\Framework\Event\NestedEvent;

class CustomerGroupTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'customer_group_translation.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var \Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\Collection\CustomerGroupTranslationBasicCollection
     */
    protected $customerGroupTranslations;

    public function __construct(CustomerGroupTranslationBasicCollection $customerGroupTranslations, Context $context)
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

    public function getCustomerGroupTranslations(): CustomerGroupTranslationBasicCollection
    {
        return $this->customerGroupTranslations;
    }
}
