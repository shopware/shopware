<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Aggregate\GoogleShoppingAdsAccount;

use Shopware\Core\Content\GoogleShopping\Aggregate\GoogleShoppingMerchantAccount\GoogleShoppingMerchantAccountEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class GoogleShoppingAdsAccountEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $merchantAccountId;

    /**
     * @var string
     */
    protected $adsId;

    /**
     * @var string
     */
    protected $adsManagerId;

    /**
     * @var array|null
     */
    protected $customFields;

    /**
     * @var GoogleShoppingMerchantAccountEntity|null
     */
    protected $merchantAccount;

    public function getMerchantAccountId(): string
    {
        return $this->merchantAccountId;
    }

    public function setAccountId(string $merchantAccountId): void
    {
        $this->merchantAccountId = $merchantAccountId;
    }

    public function getAdsManagerId(): string
    {
        return $this->adsManagerId;
    }

    public function setAdsManagerId(string $adsManagerId): void
    {
        $this->adsManagerId = $adsManagerId;
    }

    public function getAdsId(): string
    {
        return $this->adsId;
    }

    public function setAdsId(string $adsId): void
    {
        $this->adsId = $adsId;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }

    public function getMerchantAccount(): ?GoogleShoppingMerchantAccountEntity
    {
        return $this->merchantAccount;
    }

    public function setMerchantAccount(?GoogleShoppingMerchantAccountEntity $merchantAccount): void
    {
        $this->merchantAccount = $merchantAccount;
    }
}
