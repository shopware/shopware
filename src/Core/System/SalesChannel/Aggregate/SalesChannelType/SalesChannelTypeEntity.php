<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTypeTranslation\SalesChannelTypeTranslationCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

#[Package('sales-channel')]
class SalesChannelTypeEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $manufacturer;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var string|null
     */
    protected $descriptionLong;

    /**
     * @var string|null
     */
    protected $coverUrl;

    /**
     * @var string|null
     */
    protected $iconName;

    /**
     * @var array|null
     */
    protected $screenshotUrls;

    /**
     * @var SalesChannelCollection|null
     */
    protected $salesChannels;

    /**
     * @var SalesChannelTypeTranslationCollection|null
     */
    protected $translations;

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

    public function getCoverUrl(): string
    {
        return $this->coverUrl;
    }

    public function setCoverUrl(string $coverUrl): void
    {
        $this->coverUrl = $coverUrl;
    }

    public function getIconName(): string
    {
        return $this->iconName;
    }

    public function setIconName(string $iconName): void
    {
        $this->iconName = $iconName;
    }

    public function getScreenshotUrls(): array
    {
        return $this->screenshotUrls;
    }

    public function setScreenshotUrls(array $screenshotUrls): void
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
