<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountAddress;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\Header\HeaderPageletStruct;

class AccountAddressPageStruct extends Struct
{
    /**
     * @var AccountAddressPageletStruct
     */
    protected $accountAddress;

    /**
     * @var HeaderPageletStruct
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

    public function getHeader(): HeaderPageletStruct
    {
        return $this->header;
    }

    public function setHeader(HeaderPageletStruct $header): void
    {
        $this->header = $header;
    }
}
