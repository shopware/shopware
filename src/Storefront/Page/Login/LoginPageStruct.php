<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Login;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\Login\LoginPageletStruct;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;

class LoginPageStruct extends Struct
{
    /**
     * @var string|null
     */
    protected $redirectTo;

    /**
     * @var LoginPageletStruct
     */
    protected $accountLogin;

    /**
     * @var HeaderPagelet
     */
    protected $header;

    /**
     * @return LoginPageletStruct
     */
    public function getAccountLogin(): LoginPageletStruct
    {
        return $this->accountLogin;
    }

    /**
     * @param LoginPageletStruct $accountLogin
     */
    public function setAccountLogin(LoginPageletStruct $accountLogin): void
    {
        $this->accountLogin = $accountLogin;
    }

    public function getHeader(): HeaderPagelet
    {
        return $this->header;
    }

    public function setHeader(HeaderPagelet $header): void
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
