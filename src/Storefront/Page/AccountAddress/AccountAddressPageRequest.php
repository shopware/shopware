<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountAddress;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\AccountAddress\AddressPageletRequest;
use Shopware\Storefront\Pagelet\Header\HeaderPageletRequest;

class AccountAddressPageRequest extends Struct
{
    /**
     * @var null|string
     */
    protected $redirectTo;

    /**
     * @var AddressPageletRequest
     */
    protected $addressRequest;

    /**
     * @var HeaderPageletRequest
     */
    protected $headerRequest;

    public function __construct()
    {
        $this->addressRequest = new AddressPageletRequest();
        $this->headerRequest = new HeaderPageletRequest();
    }

    /**
     * @return AddressPageletRequest
     */
    public function getAddressRequest(): AddressPageletRequest
    {
        return $this->addressRequest;
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

    public function getHeaderRequest(): HeaderPageletRequest
    {
        return $this->headerRequest;
    }
}
