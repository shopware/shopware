<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

class SalesApiRouteScope extends SalesChannelApiRouteScope
{
    public const ID = 'sales-api';

    /**
     * @var string[]
     */
    protected $allowedPaths = ['sales-api'];
}
