<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountPaymentMethod;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\AccountPaymentMethod\AccountPaymentMethodPageletRequest;
use Shopware\Storefront\Pagelet\Header\HeaderPageletRequestTrait;

class AccountPaymentMethodPageRequest extends Struct
{
    use HeaderPageletRequestTrait;

    /**
     * @var AccountPaymentMethodPageletRequest
     */
    protected $accountPaymentMethodRequest;

    /**
     * @return AccountPaymentMethodPageletRequest
     */
    public function getAccountPaymentMethodRequest(): AccountPaymentMethodPageletRequest
    {
        return $this->accountPaymentMethodRequest;
    }

    /**
     * @param AccountPaymentMethodPageletRequest $accountPaymentMethodRequest
     */
    public function setAccountPaymentMethodRequest(AccountPaymentMethodPageletRequest $accountPaymentMethodRequest): void
    {
        $this->accountPaymentMethodRequest = $accountPaymentMethodRequest;
    }
}
