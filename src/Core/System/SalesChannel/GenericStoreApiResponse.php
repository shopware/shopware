<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
class GenericStoreApiResponse extends StoreApiResponse
{
    public function __construct(int $code, Struct $object)
    {
        $this->setStatusCode($code);

        parent::__construct($object);
    }
}
