<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class SalesChannelTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    /**
     * @var string
     */
    protected $salesChannelId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var array|null
     */
    protected $homeSlotConfig;

    /**
     * @var bool
     */
    protected $homeEnabled;

    /**
     * @var string|null
     */
    protected $homeName;

    /**
     * @var string|null
     */
    protected $homeMetaTitle;

    /**
     * @var string|null
     */
    protected $homeMetaDescription;

    /**
     * @var string|null
     */
    protected $homeKeywords;

    /**
     * @var SalesChannelEntity|null
     */
    protected $salesChannel;

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

    public function getHomeSlotConfig(): ?array
    {
        return $this->homeSlotConfig;
    }

    public function setHomeSlotConfig(?array $homeSlotConfig): void
    {
        $this->homeSlotConfig = $homeSlotConfig;
    }

    public function getHomeEnabled(): ?bool
    {
        return $this->homeEnabled;
    }

    public function setHomeEnabled(bool $homeEnabled): void
    {
        $this->homeEnabled = $homeEnabled;
    }

    public function getHomeName(): ?string
    {
        return $this->homeName;
    }

    public function setHomeName(?string $homeName): void
    {
        $this->homeName = $homeName;
    }

    public function getHomeMetaTitle(): ?string
    {
        return $this->homeMetaTitle;
    }

    public function setHomeMetaTitle(?string $homeMetaTitle): void
    {
        $this->homeMetaTitle = $homeMetaTitle;
    }

    public function getHomeMetaDescription(): ?string
    {
        return $this->homeMetaDescription;
    }

    public function setHomeMetaDescription(?string $homeMetaDescription): void
    {
        $this->homeMetaDescription = $homeMetaDescription;
    }

    public function getHomeKeywords(): ?string
    {
        return $this->homeKeywords;
    }

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
}
