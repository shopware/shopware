<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Struct\VariablesAccessTrait;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
abstract class StoreApiResponse extends Response
{
    // allows the cache key finder to get access of all returned data to build the cache tags
    use VariablesAccessTrait;

    /**
     * @var Struct
     */
    protected $object;

    public function __construct(Struct $object)
    {
        parent::__construct();
        $this->object = $object;
    }

    public function getObject(): Struct
    {
        return $this->object;
    }
}
