<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Struct;

use DateTime;
use Shopware\Core\Framework\Struct\Struct;

class Plugin extends Struct
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

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
    protected $descriptionLong;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var DateTime
     */
    protected $createdAt;

    /**
     * @var DateTime|null
     */
    protected $installationDate;

    /**
     * @var DateTime|null
     */
    protected $updateDate;

    /**
     * @var DateTime|null
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
     * @var string
     */
    protected $version;

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
     * @var DateTime|null
     */
    protected $storeDate;

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
     * @var string|null
     */
    protected $updateSource;

    /**
     * @var string|null
     */
    protected $updateVersion;

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId(string $id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @param string $label
     */
    public function setLabel(string $label)
    {
        $this->label = $label;
    }

    /**
     * @return null|string
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param null|string $description
     */
    public function setDescription(?string $description)
    {
        $this->description = $description;
    }

    /**
     * @return null|string
     */
    public function getDescriptionLong(): ?string
    {
        return $this->descriptionLong;
    }

    /**
     * @param null|string $descriptionLong
     */
    public function setDescriptionLong(?string $descriptionLong)
    {
        $this->descriptionLong = $descriptionLong;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @param bool $active
     */
    public function setActive(bool $active)
    {
        $this->active = $active;
    }

    /**
     * @return DateTime
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * @param DateTime $createdAt
     */
    public function setCreatedAt(DateTime $createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return DateTime|null
     */
    public function getInstallationDate(): ?\DateTime
    {
        return $this->installationDate;
    }

    /**
     * @param DateTime|null $installationDate
     */
    public function setInstallationDate(?DateTime $installationDate)
    {
        $this->installationDate = $installationDate;
    }

    /**
     * @return DateTime|null
     */
    public function getUpdateDate(): ?\DateTime
    {
        return $this->updateDate;
    }

    /**
     * @param DateTime|null $updateDate
     */
    public function setUpdateDate(?DateTime $updateDate)
    {
        $this->updateDate = $updateDate;
    }

    /**
     * @return DateTime|null
     */
    public function getRefreshDate(): ?\DateTime
    {
        return $this->refreshDate;
    }

    /**
     * @param DateTime|null $refreshDate
     */
    public function setRefreshDate(?DateTime $refreshDate)
    {
        $this->refreshDate = $refreshDate;
    }

    /**
     * @return null|string
     */
    public function getAuthor(): ?string
    {
        return $this->author;
    }

    /**
     * @param null|string $author
     */
    public function setAuthor(?string $author)
    {
        $this->author = $author;
    }

    /**
     * @return null|string
     */
    public function getCopyright(): ?string
    {
        return $this->copyright;
    }

    /**
     * @param null|string $copyright
     */
    public function setCopyright(?string $copyright)
    {
        $this->copyright = $copyright;
    }

    /**
     * @return null|string
     */
    public function getLicense(): ?string
    {
        return $this->license;
    }

    /**
     * @param null|string $license
     */
    public function setLicense(?string $license)
    {
        $this->license = $license;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @param string $version
     */
    public function setVersion(string $version)
    {
        $this->version = $version;
    }

    /**
     * @return null|string
     */
    public function getSupport(): ?string
    {
        return $this->support;
    }

    /**
     * @param null|string $support
     */
    public function setSupport(?string $support)
    {
        $this->support = $support;
    }

    /**
     * @return null|string
     */
    public function getChanges(): ?string
    {
        return $this->changes;
    }

    /**
     * @param null|string $changes
     */
    public function setChanges(?string $changes)
    {
        $this->changes = $changes;
    }

    /**
     * @return null|string
     */
    public function getLink(): ?string
    {
        return $this->link;
    }

    /**
     * @param null|string $link
     */
    public function setLink(?string $link)
    {
        $this->link = $link;
    }

    /**
     * @return null|string
     */
    public function getStoreVersion(): ?string
    {
        return $this->storeVersion;
    }

    /**
     * @param null|string $storeVersion
     */
    public function setStoreVersion(?string $storeVersion)
    {
        $this->storeVersion = $storeVersion;
    }

    /**
     * @return DateTime|null
     */
    public function getStoreDate(): ?\DateTime
    {
        return $this->storeDate;
    }

    /**
     * @param DateTime|null $storeDate
     */
    public function setStoreDate(?DateTime $storeDate)
    {
        $this->storeDate = $storeDate;
    }

    /**
     * @return bool
     */
    public function isCapabilityUpdate(): bool
    {
        return $this->capabilityUpdate;
    }

    /**
     * @param bool $capabilityUpdate
     */
    public function setCapabilityUpdate(bool $capabilityUpdate)
    {
        $this->capabilityUpdate = $capabilityUpdate;
    }

    /**
     * @return bool
     */
    public function isCapabilityInstall(): bool
    {
        return $this->capabilityInstall;
    }

    /**
     * @param bool $capabilityInstall
     */
    public function setCapabilityInstall(bool $capabilityInstall)
    {
        $this->capabilityInstall = $capabilityInstall;
    }

    /**
     * @return bool
     */
    public function isCapabilityEnable(): bool
    {
        return $this->capabilityEnable;
    }

    /**
     * @param bool $capabilityEnable
     */
    public function setCapabilityEnable(bool $capabilityEnable)
    {
        $this->capabilityEnable = $capabilityEnable;
    }

    /**
     * @return null|string
     */
    public function getUpdateSource(): ?string
    {
        return $this->updateSource;
    }

    /**
     * @param null|string $updateSource
     */
    public function setUpdateSource(?string $updateSource)
    {
        $this->updateSource = $updateSource;
    }

    /**
     * @return null|string
     */
    public function getUpdateVersion(): ?string
    {
        return $this->updateVersion;
    }

    /**
     * @param null|string $updateVersion
     */
    public function setUpdateVersion(?string $updateVersion)
    {
        $this->updateVersion = $updateVersion;
    }
}
