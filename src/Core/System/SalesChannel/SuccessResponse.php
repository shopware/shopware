<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;

/**
 * @extends StoreApiResponse<ArrayStruct>
 */
#[Package('framework')]
class SuccessResponse extends StoreApiResponse
{
    public function __construct()
    {
        parent::__construct(new ArrayStruct(['success' => true]));
    }
}
