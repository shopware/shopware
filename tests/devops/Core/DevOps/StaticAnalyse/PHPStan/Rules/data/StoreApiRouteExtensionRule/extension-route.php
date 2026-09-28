<?php declare(strict_types=1);

namespace Shopware\Commercial\StoreApiRouteExtensionRuleFixtures;

use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ExtensionRoute
{
    #[Route('/extension-route')]
    public function load(): string
    {
        return 'existing extension route';
    }
}
