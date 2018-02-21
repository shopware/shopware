<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Event\CustomerGroupTranslation;

use Shopware\Api\Customer\Collection\CustomerGroupTranslationBasicCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;

class CustomerGroupTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'customer_group_translation.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var CustomerGroupTranslationBasicCollection
     */
    protected $customerGroupTranslations;

    public function __construct(CustomerGroupTranslationBasicCollection $customerGroupTranslations, ShopContext $context)
    {
        $this->context = $context;
        $this->customerGroupTranslations = $customerGroupTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getCustomerGroupTranslations(): CustomerGroupTranslationBasicCollection
    {
        return $this->customerGroupTranslations;
    }
}
