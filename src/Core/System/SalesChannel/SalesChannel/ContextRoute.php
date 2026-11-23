<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannel;

use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Extension\ContextRouteExtension;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Attribute\Route;

#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ContextRoute extends AbstractContextRoute
{
    /**
     * @internal
     */
    public function __construct(private readonly ExtensionDispatcher $extensions)
    {
    }

    public function getDecorated(): AbstractContextRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/context', name: 'store-api.context', methods: ['GET'])]
    public function load(SalesChannelContext $context): ContextLoadRouteResponse
    {
        return $this->extensions->publish(
            name: ContextRouteExtension::NAME,
            extension: new ContextRouteExtension($context),
            function: $this->_load(...),
        );
    }

    private function _load(SalesChannelContext $context): ContextLoadRouteResponse
    {
        $response = new ContextLoadRouteResponse($context);
        $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $context->getToken());

        return $response;
    }
}
