<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerCollection;
use Shopware\Core\Content\NewsletterReceiver\NewsletterReceiverCollection;
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
    protected $name;

    /**
     * @var \DateTimeInterface|null
     */
    protected $createdAt;

    /**
     * @var \DateTimeInterface|null
     */
    protected $updatedAt;

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
     * @var NewsletterReceiverCollection|null
     */
    protected $newsletterReceivers;

    public function getSalutationKey(): string
    {
        return $this->salutationKey;
    }

    public function setSalutationKey(string $salutationKey): void
    {
        $this->salutationKey = $salutationKey;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
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

    public function setCustomers(?CustomerCollection $customers): void
    {
        $this->customers = $customers;
    }

    public function getCustomerAddresses(): ?CustomerAddressCollection
    {
        return $this->customerAddresses;
    }

    public function setCustomerAddresses(?CustomerAddressCollection $customerAddresses): void
    {
        $this->customerAddresses = $customerAddresses;
    }

    public function getOrderCustomers(): ?OrderCustomerCollection
    {
        return $this->orderCustomers;
    }

    public function setOrderCustomers(?OrderCustomerCollection $orderCustomers): void
    {
        $this->orderCustomers = $orderCustomers;
    }

    public function getOrderAddresses(): ?OrderAddressCollection
    {
        return $this->orderAddresses;
    }

    public function setOrderAddresses(?OrderAddressCollection $orderAddresses): void
    {
        $this->orderAddresses = $orderAddresses;
    }

    public function getNewsletterReceivers(): ?NewsletterReceiverCollection
    {
        return $this->newsletterReceivers;
    }

    public function setNewsletterReceivers(NewsletterReceiverCollection $newsletterReceivers): void
    {
        $this->newsletterReceivers = $newsletterReceivers;
    }
}
