<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Struct\Struct;

class StoreUpdatesStruct extends Struct
{
    /**
     * @var string
     */
    protected $name;

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
}
