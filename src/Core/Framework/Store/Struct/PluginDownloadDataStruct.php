<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
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
