<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountLogin;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\AccountLogin\LoginPageletRequest;
use Shopware\Storefront\Pagelet\AccountRegistration\RegistrationPageletRequest;
use Shopware\Storefront\Pagelet\Header\HeaderPageletRequestTrait;

class LoginPageRequest extends Struct
{
    use HeaderPageletRequestTrait;

    /**
     * @var null|string
     */
    protected $redirectTo;

    /**
     * @var LoginPageletRequest
     */
    protected $loginRequest;

    /**
     * @var RegistrationPageletRequest
     */
    protected $registrationRequest;

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

    /**
     * @return LoginPageletRequest
     */
    public function getLoginRequest(): LoginPageletRequest
    {
        return $this->loginRequest;
    }

    /**
     * @param LoginPageletRequest $loginRequest
     */
    public function setLoginRequest(LoginPageletRequest $loginRequest): void
    {
        $this->loginRequest = $loginRequest;
    }

    /**
     * @return RegistrationPageletRequest
     */
    public function getRegistrationRequest(): RegistrationPageletRequest
    {
        return $this->registrationRequest;
    }

    /**
     * @param RegistrationPageletRequest $registrationRequest
     */
    public function setRegistrationRequest(RegistrationPageletRequest $registrationRequest): void
    {
        $this->registrationRequest = $registrationRequest;
    }
}
