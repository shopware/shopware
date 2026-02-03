<?php declare(strict_types=1);

use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_acl' => ['class-non-existing-permission']])]
class InvalidAclRouteInRouteAttributeController extends StorefrontController
{
    #[Route(defaults: ['_httpCache' => true, '_acl' => ['system:create', 'order:read', 'system:core:update', 'non-existing-permission']])]
    public function index(): void
    {
    }
}
