<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountPaymentMethod;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\AccountPaymentMethod\AccountPaymentMethodPageletRequest;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletRequest;

class AccountPaymentMethodPageRequest extends Struct
{
    /**
     * @var AccountPaymentMethodPageletRequest
     */
    protected $accountPaymentMethodRequest;

    /**
     * @var ContentHeaderPageletRequest
     */
    protected $headerRequest;

    public function __construct()
    {
        $this->accountPaymentMethodRequest = new AccountPaymentMethodPageletRequest();
        $this->headerRequest = new ContentHeaderPageletRequest();
    }

    /**
     * @return AccountPaymentMethodPageletRequest
     */
    public function getAccountPaymentMethodRequest(): AccountPaymentMethodPageletRequest
    {
        return $this->accountPaymentMethodRequest;
    }

    public function getHeaderRequest(): ContentHeaderPageletRequest
    {
        return $this->headerRequest;
    }
}
