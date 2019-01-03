<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountAddress;

use Shopware\Core\Framework\Struct\Struct;

class AddressPageletRequest extends Struct
{
    /**
     * @var null|string
     */
    protected $addressId;

    /**
     * @return null|string
     */
    public function getAddressId(): ?string
    {
        return $this->addressId;
    }

    /**
     * @param null|string $addressId
     */
    public function setAddressId(?string $addressId): void
    {
        $this->addressId = $addressId;
    }
}
