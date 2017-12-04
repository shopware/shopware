<?php declare(strict_types=1);

namespace Shopware\Customer\Struct;

use Shopware\Customer\Collection\CustomerBasicCollection;
use Shopware\Customer\Collection\CustomerGroupDiscountBasicCollection;
use Shopware\Customer\Collection\CustomerGroupTranslationBasicCollection;

class CustomerGroupDetailStruct extends CustomerGroupBasicStruct
{
    /**
     * @var CustomerBasicCollection
     */
    protected $customers;

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
        $this->customers = new CustomerBasicCollection();

        $this->discounts = new CustomerGroupDiscountBasicCollection();

        $this->translations = new CustomerGroupTranslationBasicCollection();
    }

    public function getCustomers(): CustomerBasicCollection
    {
        return $this->customers;
    }

    public function setCustomers(CustomerBasicCollection $customers): void
    {
        $this->customers = $customers;
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
