<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeWidening;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTypeTranslation\SalesChannelTypeTranslationCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

#[Package('discovery')]
class SalesChannelTypeEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $name = null;

    protected ?string $manufacturer = null;

    protected ?string $description = null;

    protected ?string $descriptionLong = null;

    protected ?string $coverUrl = null;

    protected ?string $iconName = null;

    /**
     * @var list<string>|null
     */
    protected ?array $screenshotUrls = null;

    protected ?SalesChannelCollection $salesChannels = null;

    protected ?SalesChannelTypeTranslationCollection $translations = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getManufacturer(): ?string
    {
        return $this->manufacturer;
    }

    public function setManufacturer(?string $manufacturer): void
    {
        $this->manufacturer = $manufacturer;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getDescriptionLong(): ?string
    {
        return $this->descriptionLong;
    }

    public function setDescriptionLong(?string $descriptionLong): void
    {
        $this->descriptionLong = $descriptionLong;
    }

    #[ReturnTypeWidening(version: 'v6.8.0', newType: '?string')]
    public function getCoverUrl(): string
    {
        /** @deprecated tag:v6.8.0 - remove this fallback condition */
        if ($this->coverUrl === null) {
            return '';
        }

        return $this->coverUrl;
    }

    public function setCoverUrl(?string $coverUrl): void
    {
        $this->coverUrl = $coverUrl;
    }

    #[ReturnTypeWidening(version: 'v6.8.0', newType: '?string')]
    public function getIconName(): string
    {
        /** @deprecated tag:v6.8.0 - remove this fallback condition */
        if ($this->iconName === null) {
            return '';
        }

        return $this->iconName;
    }

    public function setIconName(?string $iconName): void
    {
        $this->iconName = $iconName;
    }

    /**
     * @return list<string>
     */
    #[ReturnTypeWidening(version: 'v6.8.0', newType: '?array')]
    public function getScreenshotUrls(): array
    {
        /** @deprecated tag:v6.8.0 - remove this fallback condition */
        if ($this->screenshotUrls === null) {
            return [];
        }

        return $this->screenshotUrls;
    }

    /**
     * @param list<string>|null $screenshotUrls
     */
    public function setScreenshotUrls(?array $screenshotUrls): void
    {
        $this->screenshotUrls = $screenshotUrls;
    }

    public function getSalesChannels(): ?SalesChannelCollection
    {
        return $this->salesChannels;
    }

    public function setSalesChannels(SalesChannelCollection $salesChannels): void
    {
        $this->salesChannels = $salesChannels;
    }

    public function getTranslations(): ?SalesChannelTypeTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(SalesChannelTypeTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
