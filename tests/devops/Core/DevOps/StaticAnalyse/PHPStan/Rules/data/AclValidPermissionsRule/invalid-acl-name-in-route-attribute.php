<?php declare(strict_types=1);

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ACL => ['class-non-existing-permission']])]
class InvalidAclRouteInRouteAttributeController extends StorefrontController
{
    #[Route(
        defaults: [
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
            PlatformRequest::ATTRIBUTE_ACL => ['system:create', 'order:read', 'system:core:update', 'non-existing-permission'],
        ]
    )]
    public function index(): void
    {
    }
}
