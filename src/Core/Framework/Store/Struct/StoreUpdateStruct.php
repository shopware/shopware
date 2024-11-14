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
    protected $label;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $iconPath;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $version;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $changelog;

    /**
     * @var \DateTimeInterface
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $releaseDate;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $integrated;

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
}
