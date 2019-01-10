<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountOrder;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\AccountOrder\AccountOrderPageletRequest;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletRequest;

class AccountOrderPageRequest extends Struct
{
    /**
     * @var AccountOrderPageletRequest
     */
    protected $accountOrderRequest;

    /**
     * @var ContentHeaderPageletRequest
     */
    protected $headerRequest;

    public function __construct()
    {
        $this->accountOrderRequest = new AccountOrderPageletRequest();
        $this->headerRequest = new ContentHeaderPageletRequest();
    }

    /**
     * @return AccountOrderPageletRequest
     */
    public function getAccountOrderRequest(): AccountOrderPageletRequest
    {
        return $this->accountOrderRequest;
    }

    public function getHeaderRequest(): ContentHeaderPageletRequest
    {
        return $this->headerRequest;
    }
}
