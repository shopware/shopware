<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\AddressList;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Storefront\Framework\Page\GenericPage;

class CheckoutAddressListPage extends GenericPage
{
    /**
     * @var EntitySearchResult
     */
    protected $addresses;

    public function getAddresses(): EntitySearchResult
    {
        return $this->addresses;
    }

    public function setAddresses(EntitySearchResult $addresses): CheckoutAddressListPage
    {
        $this->addresses = $addresses;

        return $this;
    }
}
