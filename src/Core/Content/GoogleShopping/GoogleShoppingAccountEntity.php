<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping;

use Shopware\Core\Content\GoogleShopping\Aggregate\GoogleShoppingMerchantAccount\GoogleShoppingMerchantAccountEntity;
use Shopware\Core\Content\GoogleShopping\DataAbstractionLayer\GoogleAccountCredential;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class GoogleShoppingAccountEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $salesChannelId;

    /**
     * @var string
     */
    protected $email;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var GoogleAccountCredential
     */
    protected $credential;

    /**
     * @var SalesChannelEntity
     */
    protected $salesChannel;

    /**
     * @var array|null
     */
    protected $customFields;

    /**
     * @var GoogleShoppingMerchantAccountEntity|null
     */
    protected $googleShoppingMerchantAccount;

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSalesChannel(): SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }

    public function getCredential(): GoogleAccountCredential
    {
        return $this->credential;
    }

    public function setCredential(GoogleAccountCredential $credential): void
    {
        $this->credential = $credential;
    }

    public function getGoogleShoppingMerchantAccount(): ?GoogleShoppingMerchantAccountEntity
    {
        return $this->googleShoppingMerchantAccount;
    }

    public function setGoogleShoppingMerchantAccount(?GoogleShoppingMerchantAccountEntity $googleShoppingMerchantAccount): void
    {
        $this->googleShoppingMerchantAccount = $googleShoppingMerchantAccount;
    }
}
