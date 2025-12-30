<?php declare(strict_types=1);

use Shopware\Core\PlatformRequest;
use Symfony\Component\Routing\Attribute\Route;

class NotController
{
    #[Route(defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true, PlatformRequest::ATTRIBUTE_ACL => ['non-existing-permission']])]
    public function index(): void
    {
    }
}
