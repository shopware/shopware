<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class StoreLicenseStruct extends Struct
{
    /**
     * @var int
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $id;

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
    protected $producerName;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $technicalPluginName;

    /**
     * @var \DateTimeInterface
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $creationDate;

    /**
     * @var \DateTimeInterface
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $expirationDate;

    /**
     * @var StoreLicenseSubscriptionStruct|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $subscription;

    /**
     * @var StoreLicenseTypeStruct
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $type;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $availableVersion;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $installed;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $iconPath;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
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
