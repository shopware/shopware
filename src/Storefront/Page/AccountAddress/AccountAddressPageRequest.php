<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountAddress;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\AccountAddress\AddressPageletRequest;
use Shopware\Storefront\Pagelet\Header\HeaderPageletRequestTrait;

class AccountAddressPageRequest extends Struct
{
    use HeaderPageletRequestTrait;

    /**
     * @var null|string
     */
    protected $redirectTo;

    /**
     * @var AddressPageletRequest
     */
    protected $addressRequest;

    /**
     * @return AddressPageletRequest
     */
    public function getAddressRequest(): AddressPageletRequest
    {
        return $this->addressRequest;
    }

    /**
     * @param AddressPageletRequest $addressRequest
     */
    public function setAddressRequest(AddressPageletRequest $addressRequest): void
    {
        $this->addressRequest = $addressRequest;
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
