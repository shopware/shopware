<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller\Stub;

use Shopware\Storefront\Controller\ProductController;
use Shopware\Tests\Unit\Storefront\Controller\StorefrontControllerMockTrait;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
class ProductControllerStub extends ProductController
{
    use StorefrontControllerMockTrait;
}
