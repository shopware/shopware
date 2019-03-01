<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Plugin\Aggregate\PluginTranslation\PluginTranslationCollection;

class PluginEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $composerName;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var string|null
     */
    protected $path;

    /**
     * @var string|null
     */
    protected $author;

    /**
     * @var string|null
     */
    protected $copyright;

    /**
     * @var string|null
     */
    protected $license;

    /**
     * @var string
     */
    protected $version;

    /**
     * @var string|null
     */
    protected $upgradeVersion;

    /**
     * @var \DateTime|null
     */
    protected $installedAt;

    /**
     * @var \DateTime|null
     */
    protected $upgradedAt;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var string|null
     */
    protected $manufacturerLink;

    /**
     * @var string|null
     */
    protected $supportLink;

    /**
     * @var array|null
     */
    protected $changelog;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var PluginTranslationCollection|null
     */
    protected $translations;

    /**
     * @var PaymentMethodCollection|null
     */
    protected $paymentMethods;

    /**
     * @var array|null
     */
    protected $attributes;

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

    public function getInstalledAt(): ?\DateTime
    {
        return $this->installedAt;
    }

    public function setInstalledAt(?\DateTime $installedAt): void
    {
        $this->installedAt = $installedAt;
    }

    public function getUpgradedAt(): ?\DateTime
    {
        return $this->upgradedAt;
    }

    public function setUpgradedAt(\DateTime $upgradedAt): void
    {
        $this->upgradedAt = $upgradedAt;
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

    public function getChangelog(): ?array
    {
        return $this->changelog;
    }

    public function setChangelog(array $changelog): void
    {
        $this->changelog = $changelog;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
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

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    public function setAttributes(?array $attributes): void
    {
        $this->attributes = $attributes;
    }
}
