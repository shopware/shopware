<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class SalesChannelTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $salesChannelId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @internal (flag:FEATURE_NEXT_13504)
     *
     * @var array|null
     */
    protected $homeSlotConfig;

    /**
     * @internal (flag:FEATURE_NEXT_13504)
     *
     * @var bool
     */
    protected $homeEnabled;

    /**
     * @internal (flag:FEATURE_NEXT_13504)
     *
     * @var string|null
     */
    protected $homeName;

    /**
     * @internal (flag:FEATURE_NEXT_13504)
     *
     * @var string|null
     */
    protected $homeMetaTitle;

    /**
     * @internal (flag:FEATURE_NEXT_13504)
     *
     * @var string|null
     */
    protected $homeMetaDescription;

    /**
     * @internal (flag:FEATURE_NEXT_13504)
     *
     * @var string|null
     */
    protected $homeKeywords;

    /**
     * @var SalesChannelEntity|null
     */
    protected $salesChannel;

    /**
     * @var array|null
     */
    protected $customFields;

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    /**
     * @internal (flag:FEATURE_NEXT_13504)
     */
    public function getHomeSlotConfig(): ?array
    {
        return $this->homeSlotConfig;
    }

    /**
     * @internal (flag:FEATURE_NEXT_13504)
     */
    public function setHomeSlotConfig(?array $homeSlotConfig): void
    {
        $this->homeSlotConfig = $homeSlotConfig;
    }

    /**
     * @internal (flag:FEATURE_NEXT_13504)
     */
    public function getHomeEnabled(): ?bool
    {
        return $this->homeEnabled;
    }

    /**
     * @internal (flag:FEATURE_NEXT_13504)
     */
    public function setHomeEnabled(bool $homeEnabled): void
    {
        $this->homeEnabled = $homeEnabled;
    }

    /**
     * @internal (flag:FEATURE_NEXT_13504)
     */
    public function getHomeName(): ?string
    {
        return $this->homeName;
    }

    /**
     * @internal (flag:FEATURE_NEXT_13504)
     */
    public function setHomeName(?string $homeName): void
    {
        $this->homeName = $homeName;
    }

    /**
     * @internal (flag:FEATURE_NEXT_13504)
     */
    public function getHomeMetaTitle(): ?string
    {
        return $this->homeMetaTitle;
    }

    /**
     * @internal (flag:FEATURE_NEXT_13504)
     */
    public function setHomeMetaTitle(?string $homeMetaTitle): void
    {
        $this->homeMetaTitle = $homeMetaTitle;
    }

    /**
     * @internal (flag:FEATURE_NEXT_13504)
     */
    public function getHomeMetaDescription(): ?string
    {
        return $this->homeMetaDescription;
    }

    /**
     * @internal (flag:FEATURE_NEXT_13504)
     */
    public function setHomeMetaDescription(?string $homeMetaDescription): void
    {
        $this->homeMetaDescription = $homeMetaDescription;
    }

    /**
     * @internal (flag:FEATURE_NEXT_13504)
     */
    public function getHomeKeywords(): ?string
    {
        return $this->homeKeywords;
    }

    /**
     * @internal (flag:FEATURE_NEXT_13504)
     */
    public function setHomeKeywords(?string $homeKeywords): void
    {
        $this->homeKeywords = $homeKeywords;
    }

    public function getSalesChannel(): ?SalesChannelEntity
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
}
