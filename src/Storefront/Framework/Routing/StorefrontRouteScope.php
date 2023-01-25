<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\AbstractRouteScope;
use Shopware\Core\Framework\Routing\SalesChannelContextRouteScopeDependant;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\HttpFoundation\Request;

#[Package('storefront')]
class StorefrontRouteScope extends AbstractRouteScope implements SalesChannelContextRouteScopeDependant
{
    final public const ID = 'storefront';

    /**
     * @var array<string>
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
