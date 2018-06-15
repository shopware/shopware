<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\Search\EntitySearchResult;

class PluginStruct extends Entity
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
     * @var bool
     */
    protected $active;

    /**
     * @var string
     */
    protected $version;

    /**
     * @var bool
     */
    protected $capabilityUpdate;

    /**
     * @var bool
     */
    protected $capabilityInstall;

    /**
     * @var bool
     */
    protected $capabilityEnable;

    /**
     * @var bool
     */
    protected $capabilitySecureUninstall;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var string|null
     */
    protected $descriptionLong;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $installationDate;

    /**
     * @var \DateTime|null
     */
    protected $updateDate;

    /**
     * @var \DateTime|null
     */
    protected $refreshDate;

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
     * @var string|null
     */
    protected $support;

    /**
     * @var string|null
     */
    protected $changes;

    /**
     * @var string|null
     */
    protected $link;

    /**
     * @var string|null
     */
    protected $storeVersion;

    /**
     * @var \DateTime|null
     */
    protected $storeDate;

    /**
     * @var string|null
     */
    protected $updateSource;

    /**
     * @var string|null
     */
    protected $updateVersion;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var EntitySearchResult|null
     */
    protected $configForms;

    /**
     * @var EntitySearchResult|null
     */
    protected $paymentMethods;

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

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    public function getCapabilityUpdate(): bool
    {
        return $this->capabilityUpdate;
    }

    public function setCapabilityUpdate(bool $capabilityUpdate): void
    {
        $this->capabilityUpdate = $capabilityUpdate;
    }

    public function getCapabilityInstall(): bool
    {
        return $this->capabilityInstall;
    }

    public function setCapabilityInstall(bool $capabilityInstall): void
    {
        $this->capabilityInstall = $capabilityInstall;
    }

    public function getCapabilityEnable(): bool
    {
        return $this->capabilityEnable;
    }

    public function setCapabilityEnable(bool $capabilityEnable): void
    {
        $this->capabilityEnable = $capabilityEnable;
    }

    public function getCapabilitySecureUninstall(): bool
    {
        return $this->capabilitySecureUninstall;
    }

    public function setCapabilitySecureUninstall(bool $capabilitySecureUninstall): void
    {
        $this->capabilitySecureUninstall = $capabilitySecureUninstall;
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

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getInstallationDate(): ?\DateTime
    {
        return $this->installationDate;
    }

    public function setInstallationDate(?\DateTime $installationDate): void
    {
        $this->installationDate = $installationDate;
    }

    public function getUpdateDate(): ?\DateTime
    {
        return $this->updateDate;
    }

    public function setUpdateDate(?\DateTime $updateDate): void
    {
        $this->updateDate = $updateDate;
    }

    public function getRefreshDate(): ?\DateTime
    {
        return $this->refreshDate;
    }

    public function setRefreshDate(?\DateTime $refreshDate): void
    {
        $this->refreshDate = $refreshDate;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): void
    {
        $this->author = $author;
    }

    public function getCopyright(): ?string
    {
        return $this->copyright;
    }

    public function setCopyright(?string $copyright): void
    {
        $this->copyright = $copyright;
    }

    public function getLicense(): ?string
    {
        return $this->license;
    }

    public function setLicense(?string $license): void
    {
        $this->license = $license;
    }

    public function getSupport(): ?string
    {
        return $this->support;
    }

    public function setSupport(?string $support): void
    {
        $this->support = $support;
    }

    public function getChanges(): ?string
    {
        return $this->changes;
    }

    public function setChanges(?string $changes): void
    {
        $this->changes = $changes;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): void
    {
        $this->link = $link;
    }

    public function getStoreVersion(): ?string
    {
        return $this->storeVersion;
    }

    public function setStoreVersion(?string $storeVersion): void
    {
        $this->storeVersion = $storeVersion;
    }

    public function getStoreDate(): ?\DateTime
    {
        return $this->storeDate;
    }

    public function setStoreDate(?\DateTime $storeDate): void
    {
        $this->storeDate = $storeDate;
    }

    public function getUpdateSource(): ?string
    {
        return $this->updateSource;
    }

    public function setUpdateSource(?string $updateSource): void
    {
        $this->updateSource = $updateSource;
    }

    public function getUpdateVersion(): ?string
    {
        return $this->updateVersion;
    }

    public function setUpdateVersion(?string $updateVersion): void
    {
        $this->updateVersion = $updateVersion;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getConfigForms(): ?EntitySearchResult
    {
        return $this->configForms;
    }

    public function setConfigForms(EntitySearchResult $configForms): void
    {
        $this->configForms = $configForms;
    }

    public function getPaymentMethods(): ?EntitySearchResult
    {
        return $this->paymentMethods;
    }

    public function setPaymentMethods(EntitySearchResult $paymentMethods): void
    {
        $this->paymentMethods = $paymentMethods;
    }
}
