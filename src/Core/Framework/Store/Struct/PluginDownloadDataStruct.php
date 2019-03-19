<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Struct\Struct;

class PluginDownloadDataStruct extends Struct
{
    /**
     * @var string
     */
    protected $location;

    public function getLocation(): string
    {
        return $this->location;
    }
}
