<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Address;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\PropertyTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Page\Address\Listing\AddressListingPage;
use Shopware\Storefront\Page\Page;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class AddressEditorModalStruct extends Struct
{
    protected bool $changeBilling = false;

    protected bool $changeShipping = false;

    protected bool $success = false;

    protected ?string $addressId = null;

    /**
     * @var array<string, mixed>
     */
    protected array $messages = [];

    protected ?CustomerAddressEntity $address = null;

    // @deprecated tag:v6.8.0 - Remove the temporary @var when the native type is narrowed to ?AddressListingPage.
    /**
     * @var AddressListingPage|null
     */
    #[PropertyTypeNarrowing(version: 'v6.8.0', newType: AddressListingPage::class . '|null')]
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

    /**
     * @return array<string, mixed>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @param array<string, mixed> $messages
     */
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

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: AddressListingPage::class . '|null')]
    public function getPage(): ?Page
    {
        return $this->page;
    }

    // @phpstan-ignore shopware.codeCoverageIgnoreOnLogic (Temporary 6.8 compatibility guard in a coverage-excluded struct.)
    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'page', newType: AddressListingPage::class . '|null')]
    public function setPage(?Page $page): void
    {
        if ($page !== null && !$page instanceof AddressListingPage) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                'Passing a page other than AddressListingPage to AddressEditorModalStruct::setPage() is deprecated and will be rejected in v6.8.0.0.'
            );
        }

        // @phpstan-ignore assign.propertyType (Other Page types remain supported until the native type is narrowed in v6.8.0.)
        $this->page = $page;
    }
}
