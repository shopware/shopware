<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @package merchant-services
 *
 * @codeCoverageIgnore
 */
#[Package('merchant-services')]
class StoreLicenseTypeStruct extends Struct
{
    /**
     * @var string
     */
    protected $name;

    public function getApiAlias(): string
    {
        return 'store_license_type';
    }
}
