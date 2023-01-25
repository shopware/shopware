<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

#[Package('core')]
class StoreApiRouteScope extends AbstractRouteScope implements SalesChannelContextRouteScopeDependant
{
    final public const ID = DefinitionService::STORE_API;

    /**
     * @var array<string>
     */
    protected $allowedPaths = [DefinitionService::STORE_API];

    public function isAllowed(Request $request): bool
    {
        if (!$request->attributes->get('auth_required', false)) {
            return true;
        }

        /** @var Context $requestContext */
        $requestContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        if (!$request->attributes->get('auth_required', true)) {
            return $requestContext->getSource() instanceof SystemSource;
        }

        return $requestContext->getSource() instanceof SalesChannelApiSource;
    }

    public function getId(): string
    {
        return static::ID;
    }
}
