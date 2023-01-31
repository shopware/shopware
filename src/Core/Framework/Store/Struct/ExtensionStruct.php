<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('merchant-services')]
class ExtensionStruct extends Struct
{
    final public const EXTENSION_TYPE_APP = 'app';
    final public const EXTENSION_TYPE_PLUGIN = 'plugin';
    final public const SOURCE_LOCAL = 'local';
    final public const SOURCE_STORE = 'store';

    protected ?int $id = null;

    protected ?string $localId = null;

    /**
     * @see AppEntity::$name
     * @see PluginEntity::$name
     */
    protected string $name;

    /**
     * @see AppEntity::$label
     * @see PluginEntity::$label
     */
    protected string $label;

    /**
     * @see AppEntity::$description
     * @see PluginEntity::$description
     */
    protected ?string $description = null;

    protected ?string $shortDescription = null;

    /**
     * @see AppEntity::$author
     * @see PluginEntity::$author
     */
    protected ?string $producerName = null;

    /**
     * @see AppEntity::$license
     * @see PluginEntity::$license
     */
    protected ?string $license = null;

    /**
     * @see AppEntity::$version
     * @see PluginEntity::$version
     */
    protected ?string $version = null;

    protected ?string $latestVersion = null;

    /**
     * privacyPolicyLink from store
     *
     * @see AppEntity::$privacy
     */
    protected ?string $privacyPolicyLink = null;

    /**
     * languages property from store
     *
     * @var array<string>
     */
    protected array $languages = [];

    protected ?float $rating = null;

    protected int $numberOfRatings = 0;

    protected ?VariantCollection $variants = null;

    protected ?FaqCollection $faq = null;

    protected ?BinaryCollection $binaries = null;

    protected ?ImageCollection $images = null;

    protected ?string $icon = null;

    protected ?string $iconRaw = null;

    protected ?StoreCategoryCollection $categories = null;

    protected ?PermissionCollection $permissions = null;

    protected bool $active = false;

    /**
     * @var string 'app' | 'plugin'
     */
    protected string $type;

    protected bool $isTheme = false;

    /**
     * @see AppEntity::$configurable
     */
    protected bool $configurable = false;

    /**
     * @see AppEntity::$privacyPolicyExtensions
     */
    protected ?string $privacyPolicyExtension = null;

    protected ?LicenseStruct $storeLicense = null;

    protected ?ExtensionStruct $storeExtension = null;

    protected ?\DateTimeInterface $installedAt = null;

    protected ?\DateTimeInterface $updatedAt = null;

    protected array $notices = [];

    /**
     * Is this extension locally available or only in store
     */
    protected string $source = self::SOURCE_LOCAL;

    /**
     * Is the update local or in store
     */
    protected string $updateSource = self::SOURCE_LOCAL;

    protected bool $allowDisable = true;

    /**
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): ExtensionStruct
    {
        if (!isset($data['name'])) {
            throw new \InvalidArgumentException('Entry "name" in payload missing');
        }

        if (!isset($data['label'])) {
            throw new \InvalidArgumentException('Entry "label" in payload missing');
        }

        if (!isset($data['type'])) {
            throw new \InvalidArgumentException('Entry "type" in payload missing');
        }

        return (new self())->assign($data);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getLocalId(): ?string
    {
        return $this->localId;
    }

    public function setLocalId(?string $localId): void
    {
        $this->localId = $localId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): void
    {
        $this->shortDescription = $shortDescription;
    }

    public function getProducerName(): ?string
    {
        return $this->producerName;
    }

    public function setProducerName(string $producerName): void
    {
        $this->producerName = $producerName;
    }

    public function getLicense(): ?string
    {
        return $this->license;
    }

    public function setLicense(string $license): void
    {
        $this->license = $license;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    public function getLatestVersion(): ?string
    {
        return $this->latestVersion;
    }

    public function setLatestVersion(?string $latestVersion): void
    {
        $this->latestVersion = $latestVersion;
    }

    public function getPrivacyPolicyLink(): ?string
    {
        return $this->privacyPolicyLink;
    }

    public function setPrivacyPolicyLink(?string $privacyPolicyLink): void
    {
        $this->privacyPolicyLink = $privacyPolicyLink;
    }

    public function getVariants(): ?VariantCollection
    {
        return $this->variants;
    }

    public function setVariants(VariantCollection $variants): void
    {
        $this->variants = $variants;
    }

    /**
     * @return array<string>|null
     */
    public function getLanguages(): ?array
    {
        return $this->languages;
    }

    /**
     * @param array<string> $languages
     */
    public function setLanguages(array $languages): void
    {
        $this->languages = $languages;
    }

    public function getRating(): ?float
    {
        return $this->rating;
    }

    public function setRating(float $rating): void
    {
        $this->rating = $rating;
    }

    public function getNumberOfRatings(): int
    {
        return $this->numberOfRatings;
    }

    public function setNumberOfRatings(int $numberOfRatings): void
    {
        $this->numberOfRatings = $numberOfRatings;
    }

    public function getFaq(): ?FaqCollection
    {
        return $this->faq;
    }

    public function setFaq(FaqCollection $faq): void
    {
        $this->faq = $faq;
    }

    public function getBinaries(): ?BinaryCollection
    {
        return $this->binaries;
    }

    public function setBinaries(BinaryCollection $binaries): void
    {
        $this->binaries = $binaries;
    }

    public function getImages(): ?ImageCollection
    {
        return $this->images;
    }

    public function setImages(ImageCollection $images): void
    {
        $this->images = $images;
    }

    public function getCategories(): ?StoreCategoryCollection
    {
        return $this->categories;
    }

    public function setCategories(StoreCategoryCollection $categories): void
    {
        $this->categories = $categories;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): void
    {
        $this->icon = $icon;
    }

    public function getIconRaw(): ?string
    {
        return $this->iconRaw;
    }

    public function setIconRaw(string $iconRaw): void
    {
        $this->iconRaw = $iconRaw;
    }

    public function getPermissions(): ?PermissionCollection
    {
        return $this->permissions;
    }

    public function setPermissions(?PermissionCollection $permissions): void
    {
        $this->permissions = $permissions;
    }

    public function getCategorizedPermissions(): array
    {
        if ($this->permissions === null) {
            return [];
        }

        return $this->permissions->getCategorizedPermissions();
    }

    public function jsonSerialize(): array
    {
        $vars = get_object_vars($this);
        $vars['permissions'] = $this->getCategorizedPermissions();

        return $vars;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function isTheme(): bool
    {
        return $this->isTheme;
    }

    public function setIsTheme(bool $isTheme): void
    {
        $this->isTheme = $isTheme;
    }

    public function isConfigurable(): bool
    {
        return $this->configurable;
    }

    public function setConfigurable(bool $configurable): void
    {
        $this->configurable = $configurable;
    }

    public function getPrivacyPolicyExtension(): ?string
    {
        return $this->privacyPolicyExtension;
    }

    public function setPrivacyPolicyExtension(?string $privacyPolicyExtension): void
    {
        $this->privacyPolicyExtension = $privacyPolicyExtension;
    }

    public function getStoreLicense(): ?LicenseStruct
    {
        return $this->storeLicense;
    }

    public function setStoreLicense(?LicenseStruct $storeLicense): void
    {
        $this->storeLicense = $storeLicense;
    }

    public function getInstalledAt(): ?\DateTimeInterface
    {
        return $this->installedAt;
    }

    public function setInstalledAt(?\DateTimeInterface $installedAt): void
    {
        $this->installedAt = $installedAt;
    }

    public function getNotices(): array
    {
        return $this->notices;
    }

    public function setNotices(array $notices): void
    {
        $this->notices = $notices;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): void
    {
        $this->source = $source;
    }

    public function getUpdateSource(): string
    {
        return $this->updateSource;
    }

    public function setUpdateSource(string $updateSource): void
    {
        $this->updateSource = $updateSource;
    }

    public function getStoreExtension(): ?ExtensionStruct
    {
        return $this->storeExtension;
    }

    public function setStoreExtension(?ExtensionStruct $storeExtension): void
    {
        $this->storeExtension = $storeExtension;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function isAllowDisable(): bool
    {
        return $this->allowDisable;
    }

    public function setAllowDisable(bool $allowDisable): void
    {
        $this->allowDisable = $allowDisable;
    }
}
