<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountOrder;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\AccountOrder\AccountOrderPageletRequest;
use Shopware\Storefront\Pagelet\Header\HeaderPageletRequestTrait;

class AccountOrderPageRequest extends Struct
{
    use HeaderPageletRequestTrait;

    /**
     * @var AccountOrderPageletRequest
     */
    protected $accountOrderRequest;

    /**
     * @return AccountOrderPageletRequest
     */
    public function getAccountOrderRequest(): AccountOrderPageletRequest
    {
        return $this->accountOrderRequest;
    }

    /**
     * @param AccountOrderPageletRequest $accountOrderRequest
     */
    public function setAccountOrderRequest(AccountOrderPageletRequest $accountOrderRequest): void
    {
        $this->accountOrderRequest = $accountOrderRequest;
    }
}
