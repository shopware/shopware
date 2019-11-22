<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Routing\AbstractRouteScope;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\HttpFoundation\Request;

class StorefrontRouteScope extends AbstractRouteScope
{
    public const ID = 'storefront';

    /**
     * @var string[]
     */
    protected $allowedPaths = [];

    public function isAllowed(Request $request): bool
    {
        return $request->attributes->has(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)
            && $request->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST) === true
        ;
    }

    public function getId(): string
    {
        return self::ID;
    }
}
