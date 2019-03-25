<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Profile;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Storefront\Framework\Page\PageWithHeader;

class AccountProfilePage extends PageWithHeader
{
    /**
     * @var CustomerEntity
     */
    protected $customer;

    /**
     * @var SalutationCollection
     */
    protected $salutations;

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    public function getSalutations(): SalutationCollection
    {
        return $this->salutations;
    }

    public function setSalutations(SalutationCollection $salutations): void
    {
        $this->salutations = $salutations;
    }
}
