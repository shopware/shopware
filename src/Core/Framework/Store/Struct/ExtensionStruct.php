<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
class ExtensionStruct extends Struct
{
    public const EXTENSION_TYPE_APP = 'app';
    public const EXTENSION_TYPE_PLUGIN = 'plugin';

    /**
     * @var int|null is null for private extensions
     */
    protected $id;

    /**
     * @var string|null is null for not installed extensions
     */
    protected $localId;

    /**
     * @see AppEntity::$name
     * @see PluginEntity::$name
     *
     * @var string
     */
    protected $name;

    /**
     * @see AppEntity::$label
     * @see PluginEntity::$label
     *
     * @var string
     */
    protected $label;

    /**
     * @see AppEntity::$description
     * @see PluginEntity::$description
     *
     * @var string
     */
    protected $description;

    /**
     * @var string|null is null for private extensions
     */
    protected $shortDescription;

    /**
     * @see AppEntity::$author
     * @see PluginEntity::$author
     *
     * @var string
     */
    protected $producerName;

    /**
     * @see AppEntity::$license
     * @see PluginEntity::$license
     *
     * @var string
     */
    protected $license;

    /**
     * @see AppEntity::$version
     * @see PluginEntity::$version
     *
     * @var string|null for store details
     */
    protected $version;

    /**
     * @var string|null for private extensions
     */
    protected $latestVersion;

    /**
     * privacyPolicyLink from store
     *
     * @see AppEntity::$privacy
     *
     * @var string|null null for plugins
     */
    protected $privacyPolicyLink;

    /**
     * languages property from store
     *
     * @var array<string>
     */
    protected $languages = [];

    /**
     * @var float|null null for private extensions
     */
    protected $rating;

    /**
     * @var int total of given ratings
     */
    protected $numberOfRatings = 0;

    /**
     * @var VariantCollection|null
     */
    protected $variants;

    /**
     * @var FaqCollection|null
     */
    protected $faq;

    /**
     * @var BinaryCollection|null
     */
    protected $binaries;

    /**
     * @var ImageCollection
     */
    protected $images;

    /**
     * @var string|null
     */
    protected $icon;

    /**
     * @var string|null
     */
    protected $iconRaw;

    /**
     * @var StoreCategoryCollection|null
     */
    protected $categories;

    /**
     * @var PermissionCollection|null
     */
    protected $permissions;

    /**
     * @var bool
     */
    protected $active = false;

    /**
     * @var string 'app' | 'plugin'
     */
    protected $type;

    /**
     * @var bool
     */
    protected $isTheme;

    /**
     * @see AppEntity::$configurable
     *
     * @var bool
     */
    protected $configurable;

    /**
     * @see AppEntity::$privacyPolicyExtensions
     *
     * @var string|null
     */
    protected $privacyPolicyExtensions;

    public static function fromArray(array $data): ExtensionStruct
    {
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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
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

    public function getProducerName(): string
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
     * @return array<string>
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

    public function getImages(): ImageCollection
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

    public function setIcon(string $icon): void
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

    public function getPrivacyPolicyExtensions(): ?string
    {
        return $this->privacyPolicyExtensions;
    }

    public function setPrivacyPolicyExtensions(?string $privacyPolicyExtensions): void
    {
        $this->privacyPolicyExtensions = $privacyPolicyExtensions;
    }
}
