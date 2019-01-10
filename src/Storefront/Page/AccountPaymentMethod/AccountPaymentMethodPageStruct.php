<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountPaymentMethod;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\AccountPaymentMethod\AccountPaymentMethodPageletStruct;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletStruct;

class AccountPaymentMethodPageStruct extends Struct
{
    /**
     * @var AccountPaymentMethodPageletStruct
     */
    protected $accountPaymentMethod;

    /**
     * @var ContentHeaderPageletStruct
     */
    protected $header;

    /**
     * @return AccountPaymentMethodPageletStruct
     */
    public function getAccountPaymentMethod(): AccountPaymentMethodPageletStruct
    {
        return $this->accountPaymentMethod;
    }

    /**
     * @param AccountPaymentMethodPageletStruct $accountPaymentMethod
     */
    public function setAccountPaymentMethod(AccountPaymentMethodPageletStruct $accountPaymentMethod): void
    {
        $this->accountPaymentMethod = $accountPaymentMethod;
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
