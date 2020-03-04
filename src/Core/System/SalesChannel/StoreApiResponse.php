<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\HttpFoundation\Response;

abstract class StoreApiResponse extends Response
{
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
