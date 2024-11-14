<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;

#[Package('core')]
class SuccessResponse extends StoreApiResponse
{
    /**
     * @var ArrayStruct<string, mixed>
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $object;

    public function __construct()
    {
        parent::__construct(new ArrayStruct(['success' => true]));
    }
}
