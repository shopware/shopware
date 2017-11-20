<?php declare(strict_types=1);

namespace Shopware\Customer\Event\CustomerGroupTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Collection\CustomerGroupTranslationBasicCollection;
use Shopware\Framework\Event\NestedEvent;

class CustomerGroupTranslationBasicLoadedEvent extends NestedEvent
{
    const NAME = 'customer_group_translation.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CustomerGroupTranslationBasicCollection
     */
    protected $customerGroupTranslations;

    public function __construct(CustomerGroupTranslationBasicCollection $customerGroupTranslations, TranslationContext $context)
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

    public function getCustomerGroupTranslations(): CustomerGroupTranslationBasicCollection
    {
        return $this->customerGroupTranslations;
    }
}
