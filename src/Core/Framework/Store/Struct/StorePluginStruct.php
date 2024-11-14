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
    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $name;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $label;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $shortDescription;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $iconPath;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $version;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $description;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $changelog;

    /**
     * @var \DateTimeInterface|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $releaseDate;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $installed = false;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $active = false;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $language;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $region;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $category;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $manufacturer;

    /**
     * @var int|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $position;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $isCategoryLead;

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
