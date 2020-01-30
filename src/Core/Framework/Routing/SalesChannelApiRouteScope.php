<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

class SalesChannelApiRouteScope extends AbstractRouteScope implements SalesChannelContextRouteScopeDependant
{
    public const ID = 'sales-channel-api';

    /**
     * @var string[]
     */
    protected $allowedPaths = ['sales-channel-api'];

    public function isAllowed(Request $request): bool
    {
        /** @var Context $requestContext */
        $requestContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        return $requestContext->getSource() instanceof SalesChannelApiSource;
    }

    public function getId(): string
    {
        return self::ID;
    }
}
