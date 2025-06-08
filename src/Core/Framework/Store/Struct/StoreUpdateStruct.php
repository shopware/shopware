<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class StoreUpdateStruct extends Struct
{
    protected string $name;

    protected string $label;

    protected string $iconPath;

    protected string $version;

    protected string $changelog;

    protected \DateTimeInterface $releaseDate;

    protected bool $integrated;

    protected string $inAppFeatures = '';

    public function getApiAlias(): string
    {
        return 'store_update';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getIconPath(): string
    {
        return $this->iconPath;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getChangelog(): string
    {
        return $this->changelog;
    }

    public function getReleaseDate(): \DateTimeInterface
    {
        return $this->releaseDate;
    }

    public function isIntegrated(): bool
    {
        return $this->integrated;
    }

    public function getInAppFeatures(): string
    {
        return $this->inAppFeatures;
    }

    public function setInAppFeatures(string $inAppFeatures): void
    {
        $this->inAppFeatures = $inAppFeatures;
    }
}
