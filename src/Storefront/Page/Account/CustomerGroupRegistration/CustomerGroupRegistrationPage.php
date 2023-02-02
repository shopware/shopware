<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\CustomerGroupRegistration;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Storefront\Page\Account\Login\AccountLoginPage;

class CustomerGroupRegistrationPage extends AccountLoginPage
{
    /**
     * @var CustomerGroupEntity
     */
    protected $customerGroup;

    public function setGroup(CustomerGroupEntity $customerGroup): void
    {
        $this->customerGroup = $customerGroup;
    }

    public function getGroup(): CustomerGroupEntity
    {
        return $this->customerGroup;
    }
}
