<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Address;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Page\Page;

/**
 * @codeCoverageIgnore
 */
#[Package('storefront')]
class AddressEditorModalStruct extends Struct
{
    protected bool $changeBilling = false;

    protected bool $changeShipping = false;

    protected bool $success = false;

    protected ?string $addressId = null;

    protected array $messages = [];

    protected ?CustomerAddressEntity $address = null;

    protected ?Page $page = null;

    public function isChangeBilling(): bool
    {
        return $this->changeBilling;
    }

    public function setChangeBilling(bool $changeBilling): void
    {
        $this->changeBilling = $changeBilling;
    }

    public function isChangeShipping(): bool
    {
        return $this->changeShipping;
    }

    public function setChangeShipping(bool $changeShipping): void
    {
        $this->changeShipping = $changeShipping;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    public function getAddressId(): ?string
    {
        return $this->addressId;
    }

    public function setAddressId(?string $addressId): void
    {
        $this->addressId = $addressId;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function setMessages(array $messages): void
    {
        $this->messages = $messages;
    }

    public function getAddress(): ?CustomerAddressEntity
    {
        return $this->address;
    }

    public function setAddress(?CustomerAddressEntity $address): void
    {
        $this->address = $address;
    }

    public function getPage(): ?Page
    {
        return $this->page;
    }

    public function setPage(?Page $page): void
    {
        $this->page = $page;
    }
}
