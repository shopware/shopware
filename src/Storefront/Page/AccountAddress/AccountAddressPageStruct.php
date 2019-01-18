<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountAddress;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\AccountAddress\AccountAddressPageletStruct;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;

class AccountAddressPageStruct extends Struct
{
    /**
     * @var AccountAddressPageletStruct
     */
    protected $accountAddress;

    /**
     * @var HeaderPagelet
     */
    protected $header;

    /**
     * @return AccountAddressPageletStruct
     */
    public function getAccountAddress(): AccountAddressPageletStruct
    {
        return $this->accountAddress;
    }

    /**
     * @param AccountAddressPageletStruct $accountAddress
     */
    public function setAccountAddress(AccountAddressPageletStruct $accountAddress): void
    {
        $this->accountAddress = $accountAddress;
    }

    public function getHeader(): HeaderPagelet
    {
        return $this->header;
    }

    public function setHeader(HeaderPagelet $header): void
    {
        $this->header = $header;
    }
}
