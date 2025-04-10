<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;

/**
 * @extends StoreApiResponse<ArrayStruct<array{}>>
 */
#[Package('framework')]
class NoContentResponse extends StoreApiResponse
{
    public function __construct()
    {
        parent::__construct(new ArrayStruct());
        $this->setStatusCode(self::HTTP_NO_CONTENT);
    }
}
