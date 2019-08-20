<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Struct\Struct;

class StoreActionStruct extends Struct
{
    /**
     * @var string
     */
    protected $label;

    /**
     * @var string
     */
    protected $externalLink;
}
