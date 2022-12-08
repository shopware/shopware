<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Framework\Struct\ArrayStruct;

/**
 * @package core
 */
class SuccessResponse extends StoreApiResponse
{
    /**
     * @var ArrayStruct<string, mixed>
     */
    protected $object;

    public function __construct()
    {
        parent::__construct(new ArrayStruct(['success' => true]));
    }
}
