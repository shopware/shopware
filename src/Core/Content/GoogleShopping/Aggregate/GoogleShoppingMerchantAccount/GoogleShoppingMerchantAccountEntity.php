<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Aggregate\GoogleShoppingMerchantAccount;

use Shopware\Core\Content\GoogleShopping\GoogleShoppingAccountEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class GoogleShoppingMerchantAccountEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $accountId;

    /**
     * @var string
     */
    protected $merchantId;

    /**
     * @var array|null
     */
    protected $customFields;

    /**
     * @var GoogleShoppingAccountEntity|null
     */
    protected $account;

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function setAccountId(string $accountId): void
    {
        $this->accountId = $accountId;
    }

    public function getMerchantId(): string
    {
        return $this->merchantId;
    }

    public function setMerchantId(string $merchantId): void
    {
        $this->merchantId = $merchantId;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }

    public function getAccount(): ?GoogleShoppingAccountEntity
    {
        return $this->account;
    }

    public function setAccount(?GoogleShoppingAccountEntity $account): void
    {
        $this->account = $account;
    }
}
