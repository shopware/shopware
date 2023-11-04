<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('merchant-services')]
class StoreUpdateStruct extends Struct
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
     * @var string
     */
    protected $iconPath;

    /**
     * @var string
     */
    protected $version;

    /**
     * @var string
     */
    protected $changelog;

    /**
     * @var \DateTimeInterface
     */
    protected $releaseDate;

    /**
     * @var bool
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
