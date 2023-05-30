<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('merchant-services')]
class StorePluginStruct extends Struct
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var string
     */
    protected $shortDescription;

    /**
     * @var string|null
     */
    protected $iconPath;

    /**
     * @var string|null
     */
    protected $version;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var string|null
     */
    protected $changelog;

    /**
     * @var \DateTimeInterface|null
     */
    protected $releaseDate;

    /**
     * @var bool
     */
    protected $installed = false;

    /**
     * @var bool
     */
    protected $active = false;

    /**
     * @var string|null
     */
    protected $language;

    /**
     * @var string|null
     */
    protected $region;

    /**
     * @var string|null
     */
    protected $category;

    /**
     * @var string|null
     */
    protected $manufacturer;

    /**
     * @var int|null
     */
    protected $position;

    /**
     * @var bool
     */
    protected $isCategoryLead;

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getShortDescription(): string
    {
        return $this->shortDescription;
    }

    public function getIconPath(): ?string
    {
        return $this->iconPath;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getChangelog(): ?string
    {
        return $this->changelog;
    }

    public function getReleaseDate(): ?\DateTimeInterface
    {
        return $this->releaseDate;
    }

    public function isInstalled(): bool
    {
        return $this->installed;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function getManufacturer(): ?string
    {
        return $this->manufacturer;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function isCategoryLead(): bool
    {
        return $this->isCategoryLead;
    }

    public function getApiAlias(): string
    {
        return 'store_plugin';
    }
}
