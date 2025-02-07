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
    protected int $id;

    protected string $name;

    protected string $producerName;

    protected string $technicalPluginName;

    protected \DateTimeInterface $creationDate;

    protected \DateTimeInterface $expirationDate;

    protected ?StoreLicenseSubscriptionStruct $subscription;

    protected StoreLicenseTypeStruct $type;

    protected string $availableVersion;

    protected bool $installed;

    protected string $iconPath;

    protected bool $updateAvailable;

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
