<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('services-settings')]
class PluginDownloadDataStruct extends Struct
{
    /**
     * @var string
     */
    protected $location;

    /**
     * @var string
     */
    protected $type;

    protected ?int $size = null;

    protected ?string $sha1 = null;

    protected ?string $binaryVersion = null;

    protected ?string $manifestLocation = null;

    public function getLocation(): string
    {
        return $this->location;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getApiAlias(): string
    {
        return 'store_download_data';
    }
}
