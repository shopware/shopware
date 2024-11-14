<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductReview;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[Package('inventory')]
class ProductReviewEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $productId;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $customerId;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $salesChannelId;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $languageId;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $externalUser;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $externalEmail;

    /**
     * @var float|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $points;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $status;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $comment;

    /**
     * @var SalesChannelEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $salesChannel;

    /**
     * @var LanguageEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $language;

    /**
     * @var CustomerEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $customer;

    /**
     * @var ProductEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $product;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $content;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $title;

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function setCustomerId(?string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getExternalUser(): ?string
    {
        return $this->externalUser;
    }

    public function setExternalUser(?string $externalUser): void
    {
        $this->externalUser = $externalUser;
    }

    public function getExternalEmail(): ?string
    {
        return $this->externalEmail;
    }

    public function setExternalEmail(?string $externalEmail): void
    {
        $this->externalEmail = $externalEmail;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): void
    {
        $this->content = $content;
    }

    public function getPoints(): ?float
    {
        return $this->points;
    }

    public function setPoints(?float $points): void
    {
        $this->points = $points;
    }

    public function getStatus(): ?bool
    {
        return $this->status;
    }

    public function setStatus(?bool $status): void
    {
        $this->status = $status;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(?CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(?LanguageEntity $language): void
    {
        $this->language = $language;
    }
}
