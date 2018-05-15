<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Event\CustomerGroupTranslation;

use Shopware\Checkout\Customer\Collection\CustomerGroupTranslationBasicCollection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class CustomerGroupTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'customer_group_translation.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var CustomerGroupTranslationBasicCollection
     */
    protected $customerGroupTranslations;

    public function __construct(CustomerGroupTranslationBasicCollection $customerGroupTranslations, ApplicationContext $context)
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

    public function getCustomerGroupTranslations(): CustomerGroupTranslationBasicCollection
    {
        return $this->customerGroupTranslations;
    }
}
