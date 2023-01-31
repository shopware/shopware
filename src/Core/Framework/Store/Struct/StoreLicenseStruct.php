<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('merchant-services')]
class StoreLicenseStruct extends Struct
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $producerName;

    /**
     * @var string
     */
    protected $technicalPluginName;

    /**
     * @var \DateTimeInterface
     */
    protected $creationDate;

    /**
     * @var \DateTimeInterface
     */
    protected $expirationDate;

    /**
     * @var StoreLicenseSubscriptionStruct|null
     */
    protected $subscription;

    /**
     * @var StoreLicenseTypeStruct
     */
    protected $type;

    /**
     * @var string
     */
    protected $availableVersion;

    /**
     * @var bool
     */
    protected $installed;

    /**
     * @var string
     */
    protected $iconPath;

    /**
     * @var bool
     */
    protected $updateAvailable;

    public function getTechnicalPluginName(): string
    {
        return $this->technicalPluginName;
    }

    public function setSubscription(?StoreLicenseSubscriptionStruct $subscription = null): void
    {
        $this->subscription = $subscription;
    }

    public function setType(StoreLicenseTypeStruct $type): void
    {
        $this->type = $type;
    }

    public function getAvailableVersion(): string
    {
        return $this->availableVersion;
    }

    public function getInstalled(): bool
    {
        return $this->installed;
    }

    public function setInstalled(bool $installed): void
    {
        $this->installed = $installed;
    }

    public function setUpdateAvailable(bool $updateAvailable): void
    {
        $this->updateAvailable = $updateAvailable;
    }

    public function getApiAlias(): string
    {
        return 'store_license';
    }
}
