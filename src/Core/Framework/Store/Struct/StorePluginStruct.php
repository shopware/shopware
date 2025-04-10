<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class StorePluginStruct extends Struct
{
    protected string $name;

    protected string $label;

    protected string $shortDescription;

    protected ?string $iconPath;

    protected ?string $version;

    protected ?string $description;

    protected ?string $changelog;

    protected ?\DateTimeInterface $releaseDate;

    protected bool $installed = false;

    protected bool $active = false;

    protected ?string $language;

    protected ?string $region;

    protected ?string $category;

    protected ?string $manufacturer;

    protected ?int $position;

    protected bool $isCategoryLead;

    /**
     * @var 'plugin'|'app'
     */
    protected string $type;

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

    public function getType(): string
    {
        return $this->type;
    }

    public function getApiAlias(): string
    {
        return 'store_plugin';
    }
}
