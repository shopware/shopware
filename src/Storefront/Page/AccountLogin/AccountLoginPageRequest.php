<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountLogin;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\AccountLogin\AccountLoginPageletRequest;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletRequest;

class AccountLoginPageRequest extends Struct
{
    /**
     * @var AccountLoginPageletRequest
     */
    protected $accountLoginRequest;

    /**
     * @var ContentHeaderPageletRequest
     */
    protected $headerRequest;

    public function __construct()
    {
        $this->accountLoginRequest = new AccountLoginPageletRequest();
        $this->headerRequest = new ContentHeaderPageletRequest();
    }

    /**
     * @return AccountLoginPageletRequest
     */
    public function getAccountLoginRequest(): AccountLoginPageletRequest
    {
        return $this->accountLoginRequest;
    }

    public function getHeaderRequest(): ContentHeaderPageletRequest
    {
        return $this->headerRequest;
    }
}
