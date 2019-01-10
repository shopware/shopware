<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountLogin;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\AccountLogin\AccountLoginPageletStruct;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletStruct;

class AccountLoginPageStruct extends Struct
{
    /**
     * @var null|string
     */
    protected $redirectTo;

    /**
     * @var AccountLoginPageletStruct
     */
    protected $accountLogin;

    /**
     * @var ContentHeaderPageletStruct
     */
    protected $header;

    /**
     * @return AccountLoginPageletStruct
     */
    public function getAccountLogin(): AccountLoginPageletStruct
    {
        return $this->accountLogin;
    }

    /**
     * @param AccountLoginPageletStruct $accountLogin
     */
    public function setAccountLogin(AccountLoginPageletStruct $accountLogin): void
    {
        $this->accountLogin = $accountLogin;
    }

    public function getHeader(): ContentHeaderPageletStruct
    {
        return $this->header;
    }

    public function setHeader(ContentHeaderPageletStruct $header): void
    {
        $this->header = $header;
    }

    /**
     * @return string
     */
    public function getRedirectTo(): string
    {
        return $this->redirectTo ?: '';
    }

    /**
     * @param string $redirectTo
     */
    public function setRedirectTo(string $redirectTo): void
    {
        $this->redirectTo = $redirectTo;
    }
}
