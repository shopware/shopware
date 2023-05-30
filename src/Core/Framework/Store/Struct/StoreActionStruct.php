<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('merchant-services')]
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

    public function getApiAlias(): string
    {
        return 'store_action';
    }
}
