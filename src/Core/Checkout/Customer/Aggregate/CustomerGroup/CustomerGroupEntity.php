<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationCollection;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

#[Package('checkout')]
class CustomerGroupEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $name;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $displayGross;

    /**
     * @var CustomerGroupTranslationCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $translations;

    /**
     * @var CustomerCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $customers;

    /**
     * @var SalesChannelCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $salesChannels;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $registrationActive;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $registrationTitle;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $registrationIntroduction;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $registrationOnlyCompanyRegistration;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $registrationSeoMetaDescription;

    /**
     * @var SalesChannelCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $registrationSalesChannels;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getDisplayGross(): bool
    {
        return $this->displayGross;
    }

    public function setDisplayGross(bool $displayGross): void
    {
        $this->displayGross = $displayGross;
    }

    public function getTranslations(): ?CustomerGroupTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(CustomerGroupTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getCustomers(): ?CustomerCollection
    {
        return $this->customers;
    }

    public function setCustomers(CustomerCollection $customers): void
    {
        $this->customers = $customers;
    }

    public function getSalesChannels(): ?SalesChannelCollection
    {
        return $this->salesChannels;
    }

    public function setSalesChannels(SalesChannelCollection $salesChannels): void
    {
        $this->salesChannels = $salesChannels;
    }

    public function getRegistrationActive(): bool
    {
        return $this->registrationActive;
    }

    public function setRegistrationActive(bool $registrationActive): void
    {
        $this->registrationActive = $registrationActive;
    }

    public function getRegistrationTitle(): string
    {
        return $this->registrationTitle;
    }

    public function setRegistrationTitle(string $registrationTitle): void
    {
        $this->registrationTitle = $registrationTitle;
    }

    public function getRegistrationIntroduction(): string
    {
        return $this->registrationIntroduction;
    }

    public function setRegistrationIntroduction(string $registrationIntroduction): void
    {
        $this->registrationIntroduction = $registrationIntroduction;
    }

    public function getRegistrationOnlyCompanyRegistration(): bool
    {
        return $this->registrationOnlyCompanyRegistration;
    }

    public function setRegistrationOnlyCompanyRegistration(bool $registrationOnlyCompanyRegistration): void
    {
        $this->registrationOnlyCompanyRegistration = $registrationOnlyCompanyRegistration;
    }

    public function getRegistrationSeoMetaDescription(): string
    {
        return $this->registrationSeoMetaDescription;
    }

    public function setRegistrationSeoMetaDescription(string $registrationSeoMetaDescription): void
    {
        $this->registrationSeoMetaDescription = $registrationSeoMetaDescription;
    }

    public function getRegistrationSalesChannels(): ?SalesChannelCollection
    {
        return $this->registrationSalesChannels;
    }

    public function setRegistrationSalesChannels(SalesChannelCollection $registrationSalesChannels): void
    {
        $this->registrationSalesChannels = $registrationSalesChannels;
    }
}
