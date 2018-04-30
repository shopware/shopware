<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Struct;

use Shopware\Api\Customer\Collection\CustomerGroupDiscountBasicCollection;
use Shopware\Api\Customer\Collection\CustomerGroupTranslationBasicCollection;

class CustomerGroupDetailStruct extends CustomerGroupBasicStruct
{
    /**
     * @var CustomerGroupDiscountBasicCollection
     */
    protected $discounts;

    /**
     * @var CustomerGroupTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {
        $this->discounts = new CustomerGroupDiscountBasicCollection();

        $this->translations = new CustomerGroupTranslationBasicCollection();
    }

    public function getDiscounts(): CustomerGroupDiscountBasicCollection
    {
        return $this->discounts;
    }

    public function setDiscounts(CustomerGroupDiscountBasicCollection $discounts): void
    {
        $this->discounts = $discounts;
    }

    public function getTranslations(): CustomerGroupTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(CustomerGroupTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
