<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountLogin;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\AccountLogin\LoginPageletStruct;
use Shopware\Storefront\Pagelet\Header\HeaderPageletStructTrait;

class AccountLoginPageStruct extends Struct
{
    use HeaderPageletStructTrait;

    /**
     * @var null|string
     */
    protected $redirectTo;

    /**
     * @var LoginPageletStruct
     */
    protected $login;

    /**
     * @return LoginPageletStruct
     */
    public function getLogin(): LoginPageletStruct
    {
        return $this->login;
    }

    /**
     * @param LoginPageletStruct $login
     */
    public function setLogin(LoginPageletStruct $login): void
    {
        $this->login = $login;
    }

    /**
     * @return null|string
     */
    public function getRedirectTo(): ?string
    {
        return $this->redirectTo;
    }

    /**
     * @param null|string $redirectTo
     */
    public function setRedirectTo(?string $redirectTo): void
    {
        $this->redirectTo = $redirectTo;
    }
}
