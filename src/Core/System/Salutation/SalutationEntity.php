<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerCollection;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\Salutation\Aggregate\SalutationTranslation\SalutationTranslationCollection;

class SalutationEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $salutationKey;

    /**
     * @var string|null
     */
    protected $displayName;

    /**
     * @var string|null
     */
    protected $letterName;

    /**
     * @var SalutationTranslationCollection|null
     */
    protected $translations;

    /**
     * @return CustomerCollection|null
     */
    protected $customers;

    /**
     * @return CustomerAddressCollection|null
     */
    protected $customerAddresses;

    /**
     * @return OrderCustomerCollection|null
     */
    protected $orderCustomers;

    /**
     * @return OrderAddressCollection|null
     */
    protected $orderAddresses;

    /**
     * @var NewsletterRecipientCollection|null
     */
    protected $newsletterRecipients;

    /**
     * @var array|null
     */
    protected $customFields;

    public function getSalutationKey(): string
    {
        return $this->salutationKey;
    }

    public function setSalutationKey(string $salutationKey): void
    {
        $this->salutationKey = $salutationKey;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): void
    {
        $this->displayName = $displayName;
    }

    public function getLetterName(): ?string
    {
        return $this->letterName;
    }

    public function setLetterName(?string $letterName): void
    {
        $this->letterName = $letterName;
    }

    public function getTranslations(): ?SalutationTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(SalutationTranslationCollection $translations): void
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

    public function getCustomerAddresses(): ?CustomerAddressCollection
    {
        return $this->customerAddresses;
    }

    public function setCustomerAddresses(CustomerAddressCollection $customerAddresses): void
    {
        $this->customerAddresses = $customerAddresses;
    }

    public function getOrderCustomers(): ?OrderCustomerCollection
    {
        return $this->orderCustomers;
    }

    public function setOrderCustomers(OrderCustomerCollection $orderCustomers): void
    {
        $this->orderCustomers = $orderCustomers;
    }

    public function getOrderAddresses(): ?OrderAddressCollection
    {
        return $this->orderAddresses;
    }

    public function setOrderAddresses(OrderAddressCollection $orderAddresses): void
    {
        $this->orderAddresses = $orderAddresses;
    }

    public function getNewsletterRecipients(): ?NewsletterRecipientCollection
    {
        return $this->newsletterRecipients;
    }

    public function setNewsletterRecipients(NewsletterRecipientCollection $newsletterRecipients): void
    {
        $this->newsletterRecipients = $newsletterRecipients;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }
}
