<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\StoreApiRouteExtensionRule;

use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
class TestRoute
{
    #[Route('/test-route')]
    public function load(): string
    {
        return 'test fixtures do not need extension events';
    }
}
