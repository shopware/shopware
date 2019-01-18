<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountOrder;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\AccountOrder\AccountOrderPageletStruct;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;

class AccountOrderPageStruct extends Struct
{
    /**
     * @var AccountOrderPageletStruct
     */
    protected $accountOrder;

    /**
     * @var HeaderPagelet
     */
    protected $header;

    /**
     * @return AccountOrderPageletStruct
     */
    public function getAccountOrder(): AccountOrderPageletStruct
    {
        return $this->accountOrder;
    }

    /**
     * @param AccountOrderPageletStruct $accountOrder
     */
    public function setAccountOrder(AccountOrderPageletStruct $accountOrder): void
    {
        $this->accountOrder = $accountOrder;
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
