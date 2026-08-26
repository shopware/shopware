<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller\Stub;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Controller\AccountProfileController;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopware\Tests\Unit\Storefront\Controller\StorefrontControllerMockTrait;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class AccountProfileControllerStub extends AccountProfileController
{
    use StorefrontControllerMockTrait;
}
