<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Aggregate\PluginTranslation\PluginTranslationCollection;

#[Package('core')]
class PluginEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    /**
     * @var class-string<Plugin>
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $baseClass;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $name;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $composerName;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $active;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $managedByComposer;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $path;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $author;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $copyright;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $license;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $version;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $upgradeVersion;

    /**
     * @var \DateTimeInterface|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $installedAt;

    /**
     * @var \DateTimeInterface|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $upgradedAt;

    /**
     * @internal
     *
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $iconRaw;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $icon;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $label;

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
    protected $manufacturerLink;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $supportLink;

    /**
     * @var PluginTranslationCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $translations;

    /**
     * @var PaymentMethodCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $paymentMethods;

    /**
     * @var array<string, array<string, list<string>|string>>
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $autoload;

    /**
     * @return class-string<Plugin>
     */
    public function getBaseClass(): string
    {
        return $this->baseClass;
    }

    /**
     * @param class-string<Plugin> $baseClass
     */
    public function setBaseClass(string $baseClass): void
    {
        $this->baseClass = $baseClass;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getComposerName(): ?string
    {
        return $this->composerName;
    }

    public function setComposerName(string $composerName): void
    {
        $this->composerName = $composerName;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getManagedByComposer(): bool
    {
        return $this->managedByComposer;
    }

    public function setManagedByComposer(bool $managedByComposer): void
    {
        $this->managedByComposer = $managedByComposer;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(string $author): void
    {
        $this->author = $author;
    }

    public function getCopyright(): ?string
    {
        return $this->copyright;
    }

    public function setCopyright(string $copyright): void
    {
        $this->copyright = $copyright;
    }

    public function getLicense(): ?string
    {
        return $this->license;
    }

    public function setLicense(string $license): void
    {
        $this->license = $license;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    public function getUpgradeVersion(): ?string
    {
        return $this->upgradeVersion;
    }

    public function setUpgradeVersion(?string $upgradeVersion): void
    {
        $this->upgradeVersion = $upgradeVersion;
    }

    public function getInstalledAt(): ?\DateTimeInterface
    {
        return $this->installedAt;
    }

    public function setInstalledAt(?\DateTimeInterface $installedAt): void
    {
        $this->installedAt = $installedAt;
    }

    public function getUpgradedAt(): ?\DateTimeInterface
    {
        return $this->upgradedAt;
    }

    public function setUpgradedAt(\DateTimeInterface $upgradedAt): void
    {
        $this->upgradedAt = $upgradedAt;
    }

    /**
     * @internal
     */
    public function getIconRaw(): ?string
    {
        $this->checkIfPropertyAccessIsAllowed('iconRaw');

        return $this->iconRaw;
    }

    /**
     * @internal
     */
    public function setIconRaw(string $iconRaw): void
    {
        $this->iconRaw = $iconRaw;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): void
    {
        $this->icon = $icon;
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

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getManufacturerLink(): ?string
    {
        return $this->manufacturerLink;
    }

    public function setManufacturerLink(string $manufacturerLink): void
    {
        $this->manufacturerLink = $manufacturerLink;
    }

    public function getSupportLink(): ?string
    {
        return $this->supportLink;
    }

    public function setSupportLink(string $supportLink): void
    {
        $this->supportLink = $supportLink;
    }

    public function getTranslations(): ?PluginTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(PluginTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getPaymentMethods(): ?PaymentMethodCollection
    {
        return $this->paymentMethods;
    }

    public function setPaymentMethods(PaymentMethodCollection $paymentMethods): void
    {
        $this->paymentMethods = $paymentMethods;
    }

    /**
     * @return array<string, array<string, list<string>|string>>
     */
    public function getAutoload(): array
    {
        return $this->autoload;
    }

    /**
     * @param array<string, array<string, list<string>|string>> $autoload
     */
    public function setAutoload(array $autoload): void
    {
        $this->autoload = $autoload;
    }

    public function jsonSerialize(): array
    {
        $serializedData = parent::jsonSerialize();
        unset($serializedData['iconRaw']);

        return $serializedData;
    }
}
