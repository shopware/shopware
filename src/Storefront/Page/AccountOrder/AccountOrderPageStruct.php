<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountOrder;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\AccountOrder\AccountOrderPageletStruct;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletStruct;

class AccountOrderPageStruct extends Struct
{
    /**
     * @var AccountOrderPageletStruct
     */
    protected $accountOrder;

    /**
     * @var ContentHeaderPageletStruct
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

    public function getHeader(): ContentHeaderPageletStruct
    {
        return $this->header;
    }

    public function setHeader(ContentHeaderPageletStruct $header): void
    {
        $this->header = $header;
    }
}
