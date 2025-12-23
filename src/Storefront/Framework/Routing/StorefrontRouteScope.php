<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\AbstractRouteScope;
use Shopware\Core\Framework\Routing\SalesChannelContextRouteScopeDependant;
use Shopware\Core\SalesChannelRequestEnum;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
class StorefrontRouteScope extends AbstractRouteScope implements SalesChannelContextRouteScopeDependant
{
    final public const ID = 'storefront';

    public function isAllowed(Request $request): bool
    {
        return $request->attributes->has(SalesChannelRequestEnum::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST->value)
            && $request->attributes->get(SalesChannelRequestEnum::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST->value) === true
        ;
    }

    public function getId(): string
    {
        return self::ID;
    }
}
