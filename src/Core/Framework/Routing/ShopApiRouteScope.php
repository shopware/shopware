<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

class ShopApiRouteScope extends SalesChannelApiRouteScope
{
    public const ID = 'shop-api';

    /**
     * @var string[]
     */
    protected $allowedPaths = ['shop-api'];
}
