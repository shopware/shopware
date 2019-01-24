<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountAddress;

use Shopware\Core\Framework\Struct\Struct;

class AccountAddressPageletRequest extends Struct
{
    /**
     * @var string|null
     */
    protected $addressId;

    /**
     * @return string|null
     */
    public function getAddressId(): ?string
    {
        return $this->addressId;
    }

    /**
     * @param string|null $addressId
     */
    public function setAddressId(?string $addressId): void
    {
        $this->addressId = $addressId;
    }
}
